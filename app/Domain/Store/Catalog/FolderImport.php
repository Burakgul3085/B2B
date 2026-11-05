<?php

namespace App\Domain\Store\Catalog;

use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProductVariant;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mağazada klasörden toplu ürün ekleme (docs/13-magaza-mimarisi.md §8.6). Klasör tarayıcıda okunur; sunucu üç adımda çalışır:
 *  1) lookup: hangi stok kodu ve barkod zaten var (var olan ürün atlanır, yükleme kaldığı yerden sürer),
 *  2) prepare: kategori yolları ve markalar eşleştirilir; onaylanırsa eksikler oluşturulur,
 *  3) product: ürün başına bir istek; ürün, seçenekleri, görselleri ve stoğu tek ürün ekleme ekranıyla aynı
 *     sınıfla (CreateStoreProduct) tek transaction'da oluşturulur. Fiyatlar sunucuda doğrulanır.
 * Var olan ürün hiçbir zaman güncellenmez; aynı ürün iki kez eklenmez.
 */
class FolderImport
{
    public const MAX_LOOKUP = 2000;

    public function __construct(private CreateStoreProduct $create, private CategoryService $categories, private BrandService $brands) {}

    /**
     * @param  string[]  $skus
     * @param  string[]  $barcodes
     * @return array{skus: array<string, int>, barcodes: array<string, string>} var olan kod => ürün id, barkod => stok kodu
     */
    public function lookup(array $skus, array $barcodes): array
    {
        $found = ['skus' => [], 'barcodes' => []];
        foreach (array_chunk(array_values(array_unique($skus)), 500) as $chunk) {
            foreach (StoreProductVariant::whereIn('sku', $chunk)->get(['product_id', 'sku']) as $variant) {
                $found['skus'][$variant->sku] = $variant->product_id;
            }
        }
        foreach (array_chunk(array_values(array_unique($barcodes)), 500) as $chunk) {
            foreach (StoreProductVariant::whereIn('barcode', $chunk)->get(['sku', 'barcode']) as $variant) {
                $found['barcodes'][$variant->barcode] = $variant->sku;
            }
        }

        return $found;
    }

    /**
     * Kategori yollarını (ör. ["Boyalar", "Pastel Boya"]) ve markaları eşleştirir. $create ise eksikler oluşturulur.
     * Ad karşılaştırması büyük/küçük harf duyarsızdır. Sonuç dizileri girdiyle aynı sıradadır (bulunamayan: null).
     *
     * @param  list<list<string>>  $paths
     * @param  list<string>  $brands
     * @return array{categories: list<int|null>, brands: list<int|null>, new_categories: int, new_brands: int}
     *
     * @throws ValidationException|DomainException
     */
    public function prepare(array $paths, array $brands, bool $create): array
    {
        $run = function () use ($paths, $brands, $create) {
            $index = [];
            foreach (StoreCategory::get(['id', 'parent_id', 'name']) as $category) {
                $index[$category->parent_id.'|'.mb_strtolower($category->name)] ??= $category->id;
            }
            $result = ['categories' => [], 'brands' => [], 'new_categories' => 0, 'new_brands' => 0];
            $missing = [];

            foreach ($paths as $path) {
                $parentId = null;
                foreach ($path as $level => $name) {
                    $key = $parentId.'|'.mb_strtolower($name);
                    if ($parentId === false || ! isset($index[$key])) {
                        $missing[mb_strtolower(implode('>', array_slice($path, 0, $level + 1)))] = true;
                        if (! $create) {
                            $parentId = false; // alt seviyeler de eksik sayılır

                            continue;
                        }
                        $index[$key] = $this->categories->create(['name' => $name, 'parent_id' => $parentId])->id;
                    }
                    $parentId = $index[$key];
                }
                $result['categories'][] = $parentId ?: null;
            }
            $result['new_categories'] = count($missing);

            $brandIndex = [];
            foreach (StoreBrand::get(['id', 'name']) as $brand) {
                $brandIndex[mb_strtolower($brand->name)] ??= $brand->id;
            }
            $newBrands = [];
            foreach ($brands as $name) {
                $key = mb_strtolower($name);
                if (! isset($brandIndex[$key])) {
                    $newBrands[$key] = true;
                    if ($create) {
                        $brandIndex[$key] = $this->brands->create(['name' => $name])->id;
                    }
                }
                $result['brands'][] = $brandIndex[$key] ?? null;
            }
            $result['new_brands'] = count($newBrands);

            return $result;
        };

        // Hepsi ya da hiçbiri; aynı anda iki yükleme aynı kategoriyi iki kez açmasın.
        $transaction = fn () => DB::transaction($run);

        return $create ? Cache::lock('store-folder-import-prepare', 120)->block(30, $transaction) : $run();
    }

    /**
     * Ürünü seçenekleri, görselleri ve stoğuyla oluşturur. İlk seçeneğin stok kodu veya barkodu zaten varsa
     * dokunulmaz ve "exists" döner: yarıda kalan yükleme güvenle tekrarlanabilir.
     *
     * @param  UploadedFile[]  $images
     * @param  array<int, array<string, mixed>>  $options
     * @return array{status: 'created'|'exists', id: int}
     *
     * @throws ValidationException|DomainException
     */
    public function product(array $productInput, array $variantInput, array $images, ?int $stock, array $options): array
    {
        $sku = trim((string) ($variantInput['sku'] ?? ''));
        $barcode = trim((string) ($variantInput['barcode'] ?? ''));
        if ($id = $this->existing($sku, $barcode)) {
            return ['status' => 'exists', 'id' => $id];
        }

        try {
            $product = $this->create->handle($productInput, $variantInput, $images, $stock, $options);
        } catch (UniqueConstraintViolationException $e) {
            // Aynı ürün aynı anda başka bir istekle eklendi.
            return ['status' => 'exists', 'id' => $this->existing($sku, $barcode) ?? throw $e];
        }

        return ['status' => 'created', 'id' => $product->id];
    }

    private function existing(string $sku, string $barcode): ?int
    {
        if ($sku === '' && $barcode === '') {
            return null;
        }

        return StoreProductVariant::where(function ($q) use ($sku, $barcode) {
            $q->when($sku !== '', fn ($q) => $q->where('sku', $sku))
                ->when($barcode !== '', fn ($q) => $q->orWhere('barcode', $barcode));
        })->value('product_id');
    }
}
