<?php

namespace App\Domain\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Domain\Support\Decimal;
use App\Domain\Tenancy\TenantContext;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Klasörden toplu ürün ekleme (docs/03-is-kurallari.md §2.4). Klasör tarayıcıda okunur; sunucu üç adımda çalışır:
 *  1) lookup: hangi ürün kodu ve barkod zaten var (var olan ürün atlanır, yükleme kaldığı yerden sürer),
 *  2) prepare: kategori yolları ve markalar eşleştirilir; onaylanırsa eksikler oluşturulur,
 *  3) product: ürün başına bir istek; tek ürün ekleme ekranıyla aynı sınıf (CreateProduct) kullanılır.
 * Var olan ürün hiçbir zaman güncellenmez; aynı ürün iki kez eklenmez.
 */
class FolderImport
{
    public const MAX_LOOKUP = 2000;

    /** Kategori seviyesi sınırı yoktur; yalnızca kategori ağacının güvenlik sınırı geçerlidir. */
    public const MAX_DEPTH = Category::MAX_DEPTH;

    public function __construct(private CreateProduct $create, private AuditLogger $audit, private TenantContext $tenant) {}

    /**
     * @param  string[]  $skus
     * @param  string[]  $barcodes
     * @return array{skus: array<string, int>, barcodes: array<string, string>} var olan kod => ürün id, barkod => ürün kodu
     */
    public function lookup(array $skus, array $barcodes): array
    {
        $found = ['skus' => [], 'barcodes' => []];
        foreach (array_chunk(array_values(array_unique($skus)), 500) as $chunk) {
            foreach (Product::whereIn('sku', $chunk)->get(['id', 'sku']) as $product) {
                $found['skus'][$product->sku] = $product->id;
            }
        }
        foreach (array_chunk(array_values(array_unique($barcodes)), 500) as $chunk) {
            foreach (Product::whereIn('barcode', $chunk)->get(['sku', 'barcode']) as $product) {
                $found['barcodes'][$product->barcode] = $product->sku;
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
     */
    public function prepare(array $paths, array $brands, bool $create): array
    {
        return DB::transaction(function () use ($paths, $brands, $create) {
            if ($create) {
                // Aynı anda iki yükleme aynı kategoriyi iki kez açmasın.
                Business::whereKey($this->tenant->businessId())->lockForUpdate()->first();
            }

            $index = [];
            foreach (Category::get(['id', 'parent_id', 'name']) as $category) {
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
                        $category = Category::create(['name' => $name, 'parent_id' => $parentId, 'sort' => 0, 'is_active' => true]);
                        $this->audit->log('catalog.category_created', $category, [], ['name' => $name, 'parent_id' => $parentId, 'source' => 'folder_import']);
                        $index[$key] = $category->id;
                    }
                    $parentId = $index[$key];
                }
                $result['categories'][] = $parentId ?: null;
            }
            $result['new_categories'] = count($missing);

            $brandIndex = [];
            foreach (Brand::get(['id', 'name']) as $brand) {
                $brandIndex[mb_strtolower($brand->name)] ??= $brand->id;
            }
            $newBrands = [];
            foreach ($brands as $name) {
                $key = mb_strtolower($name);
                if (! isset($brandIndex[$key])) {
                    $newBrands[$key] = true;
                    if ($create) {
                        $brand = Brand::create(['name' => $name, 'is_active' => true]);
                        $this->audit->log('catalog.brand_created', $brand, [], ['name' => $name, 'source' => 'folder_import']);
                        $brandIndex[$key] = $brand->id;
                    }
                }
                $result['brands'][] = $brandIndex[$key] ?? null;
            }
            $result['new_brands'] = count($newBrands);

            return $result;
        });
    }

    /**
     * Bir ürünü (veya $parentSku verilirse o ürünün yeni seçeneğini) oluşturur. Kod veya barkod zaten varsa
     * dokunulmaz ve "exists" döner: yarıda kalan yükleme güvenle tekrarlanabilir.
     *
     * @param  UploadedFile[]  $images
     * @return array{status: 'created'|'exists', id: int}
     *
     * @throws ValidationException|DomainException
     */
    public function product(array $input, array $images, ?string $stock, ?string $parentSku): array
    {
        $sku = trim((string) ($input['sku'] ?? ''));
        $barcode = trim((string) ($input['barcode'] ?? ''));
        if ($id = $this->existing($sku, $barcode)) {
            return ['status' => 'exists', 'id' => $id];
        }

        $parent = null;
        if ($parentSku !== null) {
            $parent = Product::notArchived()->where('sku', $parentSku)->first()
                ?? throw ValidationException::withMessages(['parent_sku' => 'Seçeneğin ana ürünü bulunamadı; klasörü yeniden yükleyin.']);
        }

        $price = Decimal::parse($input['list_price'] ?? null, 4);
        $input['list_price'] = $price ?? $input['list_price'] ?? null;

        try {
            $product = $this->create->handle($input, $images, $stock, null, [], [], $parent);
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

        return Product::where(function ($q) use ($sku, $barcode) {
            $q->when($sku !== '', fn ($q) => $q->where('sku', $sku))
                ->when($barcode !== '', fn ($q) => $q->orWhere('barcode', $barcode));
        })->value('id');
    }
}
