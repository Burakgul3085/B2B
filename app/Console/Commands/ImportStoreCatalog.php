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
    protected $signature = 'store:import-catalog {--file=database/data/store-catalog-2026-10.php : Veri dosyası} {--unpublished : Ürünleri taslak olarak ekle}';

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
        foreach ($data['products'] as [$parent, $child, $brandName, $name, $sku, $price, $vat, $qty, $description, $images]) {
            if (StoreProductVariant::where('sku', $sku)->exists()) {
                $skipped[] = $sku;

                continue;
            }
            $brand = StoreBrand::where('name', $brandName)->first() ?? $brands->create(['name' => $brandName]);
            $product = $products->create(
                ['name' => $name, 'category_id' => $ids[$parent][$child], 'brand_id' => $brand->id, 'description' => $description, 'is_published' => ! $this->option('unpublished')],
                ['sku' => $sku, 'price' => $price, 'vat_rate' => $vat],
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

    private function attachImage(ProductService $products, $product, string $url): void
    {
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
