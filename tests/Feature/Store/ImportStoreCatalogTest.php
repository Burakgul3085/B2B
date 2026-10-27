<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** store:import-catalog: ilk gerçek katalog (4 × 2 kategori, 16 ürün) panelle aynı servislerle yüklenir; tekrar çalıştırılabilir. */
class ImportStoreCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_file_is_imported_with_stock_and_images_and_can_run_twice(): void
    {
        Storage::fake('local');
        $png = imagecreatetruecolor(400, 400);
        imagefill($png, 0, 0, imagecolorallocate($png, 30, 100, 200));
        ob_start();
        imagepng($png);
        Http::fake(['www.kayakirtasiye.com.tr/*' => Http::response(ob_get_clean(), 200, ['Content-Type' => 'image/png'])]);

        $this->artisan('store:import-catalog')->assertSuccessful()->expectsOutputToContain('Eklenen ürün: 16');

        $this->assertSame(4, StoreCategory::whereNull('parent_id')->count());
        $this->assertSame(8, StoreCategory::whereNotNull('parent_id')->count());
        foreach (StoreCategory::whereNotNull('parent_id')->get() as $sub) {
            $this->assertSame(2, StoreProduct::where('category_id', $sub->id)->count(), $sub->name);
        }
        $this->assertSame(12, StoreBrand::count());
        $this->assertSame(16, StoreProduct::where('is_published', true)->count());
        $variant = StoreProductVariant::where('sku', 'YB-152203')->sole();
        $this->assertSame('69.00', (string) $variant->price);
        $this->assertSame(10, (int) app(StoreStockService::class)->available([$variant->id])[$variant->id]);
        $this->assertSame(1, $variant->product->images()->count());
        $this->assertSame(3, StoreProductVariant::where('sku', 'YB-KNG-DP600')->sole()->product->images()->count());

        // Tekrar çalıştırmak çift kayıt oluşturmaz.
        $this->artisan('store:import-catalog')->assertSuccessful()->expectsOutputToContain('Eklenen ürün: 0');
        $this->assertSame(16, StoreProduct::count());

        StoreSetting::put(['is_open' => '1']);
        $this->get('http://magaza.localhost/kategori/'.StoreCategory::where('name', 'Tükenmez Kalemler')->value('slug'))->assertOk()
            ->assertSee('Schneider Slider Edge XB Mavi Tükenmez Kalem');
    }
}
