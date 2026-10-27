<?php

namespace App\Console\Commands;

use App\Domain\Store\Catalog\BrandService;
use App\Domain\Store\Catalog\CategoryService;
use App\Domain\Store\Catalog\ProductService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProductVariant;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mağaza kataloğunu bir veri dosyasından yükler (database/data/store-catalog-*.php): kategori ağacı, markalar,
 * ürünler (tek seçenek, fiyat, KDV), açılış stoku ve görseller. Panelle aynı servisler kullanılır (doğrulama,
 * görsel işleme, işlem geçmişi). Var olan kategori, marka ve stok kodu atlanır; komut tekrar çalıştırılabilir.
 */
class ImportStoreCatalog extends Command
{
    protected $signature = 'store:import-catalog {--file=database/data/store-catalog-2026-10b.php : Veri dosyası} {--unpublished : Ürünleri taslak olarak ekle} {--image-dir= : Görselleri adresten indirmek yerine bu klasörden al (dosya adı: ImportStoreCatalog::localName)}';

    protected $description = 'Mağaza kataloğunu (kategori, marka, ürün, stok, görsel) veri dosyasından yükler';

    public function handle(CategoryService $categories, BrandService $brands, ProductService $products, StoreStockService $stock): int
    {
        $data = require base_path($this->option('file'));
        $ids = [];
        foreach ($data['categories'] as $parent => $children) {
            $top = StoreCategory::whereNull('parent_id')->where('name', $parent)->first() ?? $categories->create(['name' => $parent]);
            foreach ($children as $child) {
                $ids[$parent][$child] = (StoreCategory::where('parent_id', $top->id)->where('name', $child)->first()
                    ?? $categories->create(['name' => $child, 'parent_id' => $top->id]))->id;
            }
        }

        $added = 0;
        $skipped = [];
        foreach ($data['products'] as $row) {
            [$parent, $child, $brandName, $name, $sku, $price, $vat, $qty, $description, $images] = $row;
            $barcode = $row[10] ?? null;
            if ($existing = StoreProductVariant::where('sku', $sku)->first()) {
                // Daha önce görselsiz eklendiyse (ör. kaynak site indirmeyi engelledi) görseller tamamlanır.
                if (! $existing->product->images()->exists()) {
                    foreach ($images as $url) {
                        $this->attachImage($products, $existing->product, $url);
                    }
                }
                $skipped[] = $sku;

                continue;
            }
            $brand = StoreBrand::where('name', $brandName)->first() ?? $brands->create(['name' => $brandName]);
            $product = $products->create(
                ['name' => $name, 'category_id' => $ids[$parent][$child], 'brand_id' => $brand->id, 'description' => $description, 'is_published' => ! $this->option('unpublished')],
                ['sku' => $sku, 'barcode' => $barcode, 'price' => $price, 'vat_rate' => $vat],
            );
            $stock->receive($product->variants()->first(), $qty, 'Açılış stoku');
            foreach ($images as $url) {
                $this->attachImage($products, $product, $url);
            }
            $added++;
            $this->line("✓ {$name}");
        }

        $this->info("Eklenen ürün: {$added}".($skipped ? ' · zaten var: '.implode(', ', $skipped) : ''));

        return self::SUCCESS;
    }

    /** Önceden indirilen görselin dosya adı: adresin özeti + uzantı (farklı ürünlerde aynı dosya adı olabilir). */
    public static function localName(string $url): string
    {
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';

        return md5($url).'.'.$ext;
    }

    private function attachImage(ProductService $products, $product, string $url): void
    {
        // --image-dir: görseller önceden indirilmişse (kaynak site sunucudan indirmeyi engelliyorsa) klasörden alınır.
        $name = self::localName($url);
        $dir = $this->option('image-dir');
        if ($dir) {
            $local = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$name;
            if (! is_file($local)) {
                $this->warn("  görsel klasörde yok: {$name}");

                return;
            }
            $products->addImage($product, new UploadedFile($local, $name, null, null, true));

            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'urun');
        try {
            $response = Http::timeout(30)->retry(2, 500)->get($url);
            if (! $response->successful()) {
                $this->warn("  görsel alınamadı ({$response->status()}): {$url}");

                return;
            }
            file_put_contents($tmp, $response->body());
            $products->addImage($product, new UploadedFile($tmp, basename(parse_url($url, PHP_URL_PATH)), null, null, true));
        } catch (Throwable $e) {
            $this->warn('  görsel eklenemedi: '.$url.' ('.$e->getMessage().')');
        } finally {
            @unlink($tmp);
        }
    }
}
