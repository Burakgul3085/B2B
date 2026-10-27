<?php

namespace Tests\Feature\Store;

use App\Console\Commands\ImportStoreCatalog;
use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductImage;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** store:import-catalog: resmî kaynaklı katalog (6 ana, 17 alt kategori, 35 ürün) panelle aynı servislerle yüklenir; tekrar çalıştırılabilir. */
class ImportStoreCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        return require database_path('data/store-catalog-2026-10b.php');
    }

    private function png(): string
    {
        $img = imagecreatetruecolor(300, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 100, 200));
        ob_start();
        imagepng($img);

        return ob_get_clean();
    }

    public function test_catalog_file_is_imported_with_barcodes_stock_and_images_and_can_run_twice(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $data = $this->data();
        $images = array_sum(array_map(fn ($p) => count($p[9]), $data['products']));

        $this->artisan('store:import-catalog')->assertSuccessful()->expectsOutputToContain('Eklenen ürün: 35');

        $this->assertSame(6, StoreCategory::whereNull('parent_id')->count());
        $this->assertSame(17, StoreCategory::whereNotNull('parent_id')->count());
        foreach (StoreCategory::whereNotNull('parent_id')->get() as $sub) {
            $this->assertGreaterThanOrEqual(2, StoreProduct::where('category_id', $sub->id)->count(), $sub->name);
        }
        $this->assertSame(6, StoreBrand::count());
        $this->assertSame(35, StoreProduct::where('is_published', true)->count());
        $this->assertSame($images, StoreProductImage::count());

        $variant = StoreProductVariant::where('sku', 'YB-FC-DF3')->sole();
        $this->assertSame('142.80', (string) $variant->price);
        $this->assertSame('8681241089966', $variant->barcode);
        $this->assertSame(10, (int) app(StoreStockService::class)->available([$variant->id])[$variant->id]);

        // Tekrar çalıştırmak çift kayıt oluşturmaz.
        $this->artisan('store:import-catalog')->assertSuccessful()->expectsOutputToContain('Eklenen ürün: 0');
        $this->assertSame(35, StoreProduct::count());
        $this->assertSame($images, StoreProductImage::count());

        StoreSetting::put(['is_open' => '1']);
        $this->get('http://magaza.localhost/kategori/'.StoreCategory::where('name', 'Silgiler')->value('slug'))->assertOk()
            ->assertSee('Faber-Castell Dust-Free Sınav Silgisi 3&#039;lü', false);
    }

    public function test_second_batch_adds_new_categories_products_brands_and_logos(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $this->artisan('store:import-catalog')->assertSuccessful();
        $this->artisan('store:import-catalog', ['--file' => 'database/data/store-catalog-2026-10c.php'])->assertSuccessful()
            ->expectsOutputToContain('Eklenen ürün: 26 · logo: 4');

        $this->assertSame(61, StoreProduct::count());
        $this->assertSame(7, StoreCategory::whereNull('parent_id')->count());
        $this->assertSame(28, StoreCategory::whereNotNull('parent_id')->count());
        foreach (StoreCategory::whereNotNull('parent_id')->get() as $sub) {
            $this->assertGreaterThanOrEqual(2, StoreProduct::where('category_id', $sub->id)->count(), $sub->name);
        }
        $this->assertSame(10, StoreBrand::count());
        $this->assertSame(4, StoreBrand::whereNotNull('logo_path')->count());
        foreach (StoreBrand::whereNotNull('logo_path')->get() as $brand) {
            Storage::disk('local')->assertExists($brand->logo_path);
        }
        $this->assertNull(StoreProductVariant::where('sku', 'YB-EZO-01')->value('barcode')); // geçersiz barkod yazılmadı

        // Tekrar: ürün de logo da çoğalmaz.
        $this->artisan('store:import-catalog', ['--file' => 'database/data/store-catalog-2026-10c.php'])->assertSuccessful()
            ->expectsOutputToContain('Eklenen ürün: 0 · logo: 0');
    }

    public function test_images_blocked_by_the_source_are_filled_from_a_local_folder_on_a_second_run(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response('Just a moment...', 403)]);
        $this->artisan('store:import-catalog')->assertSuccessful()->expectsOutputToContain('Eklenen ürün: 35');
        $this->assertSame(0, StoreProductImage::count());

        $dir = storage_path('framework/testing/katalog-gorselleri');
        @mkdir($dir, 0777, true);
        $count = 0;
        foreach ($this->data()['products'] as $p) {
            foreach ($p[9] as $url) {
                file_put_contents($dir.'/'.ImportStoreCatalog::localName($url), $this->png());
                $count++;
            }
        }
        $this->artisan('store:import-catalog', ['--image-dir' => $dir])->assertSuccessful()->expectsOutputToContain('Eklenen ürün: 0');
        $this->assertSame($count, StoreProductImage::count());
        $this->artisan('store:import-catalog', ['--image-dir' => $dir])->assertSuccessful();
        $this->assertSame($count, StoreProductImage::count());
        array_map('unlink', glob($dir.'/*'));
        @rmdir($dir);
    }
}
