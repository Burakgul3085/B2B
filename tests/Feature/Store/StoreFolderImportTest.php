<?php

namespace Tests\Feature\Store;

use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Mağazada klasörden toplu ürün ekleme (docs/13-magaza-mimarisi.md §8.6). */
class StoreFolderImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(StoreAdmin::create(['name' => 'Yönetici', 'email' => 'y@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    public function test_page_renders_and_old_excel_import_is_gone(): void
    {
        $this->get(route('store_admin.products.folder'))->assertOk()->assertSee('Klasörden ürün ekle');
        $this->get(route('store_admin.products.index'))->assertOk()->assertSee('Klasörden ekle')->assertDontSee('Excel ile aktar');
        $this->get(route('store_admin.stock.index'))->assertOk()->assertDontSee('Excel ile güncelle');
    }

    public function test_prepare_creates_three_level_categories_and_brands_once(): void
    {
        $payload = ['categories' => [['Okul', 'Kalemler', 'Jel Kalem']], 'brands' => ['Pilot', 'pilot']];

        $this->postJson(route('store_admin.products.folder.prepare'), $payload)->assertOk()->assertJson(['categories' => [null], 'new_categories' => 3, 'new_brands' => 1]);
        $this->assertSame(0, StoreCategory::count());

        $res = $this->postJson(route('store_admin.products.folder.prepare'), $payload + ['create' => true])->assertOk()->json();
        $leaf = StoreCategory::findOrFail($res['categories'][0]);
        $this->assertSame(['Jel Kalem', 'Kalemler'], [$leaf->name, $leaf->parent->name]);
        $this->assertSame([StoreBrand::sole()->id, StoreBrand::sole()->id], $res['brands']);

        $this->postJson(route('store_admin.products.folder.prepare'), $payload + ['create' => true])->assertOk()->assertJson(['new_categories' => 0, 'new_brands' => 0]);
        $this->assertSame(3, StoreCategory::count());
        $this->postJson(route('store_admin.products.folder.prepare'), ['categories' => [['A', 'B', 'C', 'D']], 'brands' => []])->assertStatus(422);
    }

    public function test_product_with_options_images_and_stock_is_created_once(): void
    {
        $category = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
        $data = [
            'name' => 'Jel Kalem 0.7', 'category_id' => $category->id, 'variant_name' => 'Renk', 'description' => 'Hızlı kurur',
            'sku' => 'YK-MAVI', 'barcode' => '8690000000100', 'option_value' => 'Mavi', 'price' => '24,90', 'vat_rate' => '20', 'stock' => '30',
            'options' => [['option_value' => 'Kırmızı', 'sku' => 'YK-KIRMIZI', 'price' => '26,90', 'stock' => '12']],
        ];

        $id = $this->post(route('store_admin.products.folder.store'), $data + ['images' => [UploadedFile::fake()->image('1.jpg', 600, 600)]], ['Accept' => 'application/json'])
            ->assertCreated()->assertJson(['status' => 'created'])->json('id');

        $product = StoreProduct::with(['variants', 'images'])->findOrFail($id);
        $this->assertFalse($product->is_published);
        $this->assertCount(1, $product->images);
        $this->assertSame(['Mavi' => '24.90', 'Kırmızı' => '26.90'], $product->variants->pluck('price', 'option_value')->map(fn ($p) => (string) $p)->all());
        $this->assertSame(42, (int) StoreStockMovement::sum('quantity'));

        // Yeniden yükleme: var olan ürün atlanır, değişmez.
        $this->postJson(route('store_admin.products.folder.store'), ['price' => '1'] + $data)->assertOk()->assertJson(['status' => 'exists', 'id' => $id]);
        $this->assertSame(1, StoreProduct::count());
        $this->assertSame('24.90', (string) StoreProductVariant::where('sku', 'YK-MAVI')->value('price'));

        $this->postJson(route('store_admin.products.folder.lookup'), ['skus' => ['YK-KIRMIZI', 'YOK'], 'barcodes' => ['8690000000100']])
            ->assertOk()->assertExactJson(['skus' => ['YK-KIRMIZI' => $id], 'barcodes' => ['8690000000100' => 'YK-MAVI']]);
    }

    public function test_server_validates_price_and_vat_and_writes_nothing_on_error(): void
    {
        $base = ['name' => 'Silgi', 'sku' => 'YK-SILGI', 'price' => '3,50', 'vat_rate' => '20'];

        $this->postJson(route('store_admin.products.folder.store'), ['price' => '0'] + $base)->assertStatus(422)->assertJsonValidationErrors('price');
        $this->postJson(route('store_admin.products.folder.store'), ['vat_rate' => '18'] + $base)->assertStatus(422)->assertJsonValidationErrors('vat_rate');
        $this->postJson(route('store_admin.products.folder.store'), ['category_id' => 999999] + $base)->assertStatus(422)->assertJsonValidationErrors('category_id');
        // Seçenek satırındaki hata ürünün tamamını geri alır.
        $this->postJson(route('store_admin.products.folder.store'), $base + ['variant_name' => 'Renk', 'option_value' => 'Beyaz', 'options' => [['option_value' => 'Pembe', 'sku' => 'YK-SILGI', 'price' => '3']]])
            ->assertStatus(422);
        $this->assertSame(0, StoreProduct::count());

        // is_published yalnızca işaretlenince açılır.
        $id = $this->postJson(route('store_admin.products.folder.store'), $base + ['is_published' => '1'])->assertCreated()->json('id');
        $this->assertTrue(StoreProduct::findOrFail($id)->is_published);
    }
}
