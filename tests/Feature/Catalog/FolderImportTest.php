<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\BusinessModule;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/** Klasörden toplu ürün ekleme (docs/03-is-kurallari.md §2.4): eşleştirme, oluşturma, tekrar yüklemede çoğalmama ve işletme sınırı. */
class FolderImportTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private function product(array $overrides = []): array
    {
        return $overrides + [
            'sku' => '8690826123456',
            'barcode' => '8690826123456',
            'name' => 'Çantalı Pastel Boya 24 Renk',
            'description' => 'Yumuşak uçlu',
            'list_price' => '49,90',
            'is_active' => '0',
            'stock' => '120',
        ];
    }

    public function test_page_renders_and_old_excel_import_is_gone(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->get(route('business.products.folder'))->assertOk()->assertSee('Klasörden ürün ekle')->assertSee('Klasör nasıl hazırlanır?');
        $this->get(route('business.products.index'))->assertOk()->assertSee('Klasörden ekle')->assertDontSee('Excel ile aktar');
        $this->get('/toptanci/urunler/ice-aktar')->assertNotFound();
    }

    public function test_prepare_matches_existing_and_creates_missing_categories_and_brands_only_on_confirm(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');
        [$root, $brand] = $this->inTenant($business, fn () => [Category::create(['name' => 'Boyalar']), Brand::create(['name' => 'Faber-Castell'])]);

        $payload = ['categories' => [['boyalar', 'Pastel Boya'], ['Kalemler']], 'brands' => ['FABER-CASTELL', 'Pilot']];

        // Önizleme: hiçbir şey yazılmaz, eksikler sayılır.
        $this->postJson(route('business.products.folder.prepare'), $payload)->assertOk()
            ->assertJson(['categories' => [null, null], 'brands' => [$brand->id, null], 'new_categories' => 2, 'new_brands' => 1]);
        $this->assertSame(1, $this->inTenant($business, fn () => Category::count()));

        $res = $this->postJson(route('business.products.folder.prepare'), $payload + ['create' => true])->assertOk()->json();
        $this->inTenant($business, function () use ($res, $root, $brand) {
            $child = Category::findOrFail($res['categories'][0]);
            $this->assertSame(['Pastel Boya', $root->id], [$child->name, $child->parent_id]);
            $this->assertSame('Kalemler', Category::findOrFail($res['categories'][1])->name);
            $this->assertSame([$brand->id, Brand::where('name', 'Pilot')->value('id')], $res['brands']);
        });

        // Tekrar: aynı kayıtlar bulunur, yenisi açılmaz.
        $this->postJson(route('business.products.folder.prepare'), $payload + ['create' => true])->assertOk()->assertJson(['new_categories' => 0, 'new_brands' => 0]);
        $this->assertSame(3, $this->inTenant($business, fn () => Category::count()));

        // En fazla iki seviye.
        $this->postJson(route('business.products.folder.prepare'), ['categories' => [['A', 'B', 'C']], 'brands' => []])->assertStatus(422);
    }

    public function test_product_is_created_with_images_and_opening_stock_and_never_duplicated(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');
        [$category, $brand, $tax] = $this->inTenant($business, fn () => [Category::create(['name' => 'Boyalar']), Brand::create(['name' => 'Faber-Castell']), TaxRate::where('rate', '20.00')->value('id')]);

        $data = $this->product(['category_id' => $category->id, 'brand_id' => $brand->id, 'tax_rate_id' => $tax]);
        $images = [UploadedFile::fake()->image('1.jpg', 800, 600), UploadedFile::fake()->image('2.png', 400, 400)];

        $id = $this->post(route('business.products.folder.store'), $data + ['images' => $images], ['Accept' => 'application/json'])
            ->assertCreated()->assertJson(['status' => 'created'])->json('id');

        $this->inTenant($business, function () use ($id, $category, $brand) {
            $product = Product::with('images')->findOrFail($id);
            $this->assertSame(['8690826123456', '49.9000', $category->id, $brand->id, false, 'single'],
                [$product->sku, (string) $product->list_price, $product->category_id, $product->brand_id, $product->is_active, $product->sale_mode]);
            $this->assertCount(2, $product->images);
            $this->assertSame('120.000', (string) StockMovement::where('product_id', $id)->where('type', 'opening')->value('quantity'));
        });

        // Aynı ürün yeniden gönderilir (yarıda kalan yükleme): dokunulmaz, çoğalmaz.
        $this->post(route('business.products.folder.store'), $data + ['name' => 'Başka ad', 'list_price' => '1'], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['status' => 'exists', 'id' => $id]);
        $this->inTenant($business, function () {
            $this->assertSame(1, Product::count());
            $this->assertSame('Çantalı Pastel Boya 24 Renk', Product::value('name'));
        });

        $this->postJson(route('business.products.folder.lookup'), ['skus' => ['8690826123456', 'YK-YOK'], 'barcodes' => ['8690826123456']])
            ->assertOk()->assertExactJson(['skus' => ['8690826123456' => $id], 'barcodes' => ['8690826123456' => '8690826123456']]);
    }

    public function test_options_are_separate_linked_products_and_resume_adds_only_missing(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');
        $tax = $this->inTenant($business, fn () => TaxRate::where('rate', '20.00')->value('id'));
        $base = ['name' => 'Jel Kalem', 'list_price' => '15,00', 'tax_rate_id' => $tax, 'option_name' => 'Renk'];

        $mainId = $this->postJson(route('business.products.folder.store'), $base + ['sku' => 'YK-MAVI', 'option_value' => 'Mavi'])->assertCreated()->json('id');
        $this->postJson(route('business.products.folder.store'), ['list_price' => '16'] + $base + ['sku' => 'YK-KIRMIZI', 'option_value' => 'Kırmızı', 'parent_sku' => 'YK-MAVI'])->assertCreated();
        // Yeniden yüklemede var olan seçenek atlanır, eksik olan eklenir.
        $this->postJson(route('business.products.folder.store'), $base + ['sku' => 'YK-KIRMIZI', 'option_value' => 'Kırmızı', 'parent_sku' => 'YK-MAVI'])->assertOk()->assertJson(['status' => 'exists']);
        $this->postJson(route('business.products.folder.store'), $base + ['sku' => 'YK-SIYAH', 'option_value' => 'Siyah', 'parent_sku' => 'YK-MAVI'])->assertCreated();
        // Aynı değer ikinci kez eklenemez; ana ürünü olmayan seçenek reddedilir.
        $this->postJson(route('business.products.folder.store'), $base + ['sku' => 'YK-MAVI2', 'option_value' => 'mavi', 'parent_sku' => 'YK-MAVI'])->assertStatus(422);
        $this->postJson(route('business.products.folder.store'), $base + ['sku' => 'YK-X', 'option_value' => 'Yeşil', 'parent_sku' => 'YOK'])->assertStatus(422)->assertJsonValidationErrors('parent_sku');

        $this->inTenant($business, function () use ($mainId) {
            $group = Product::findOrFail($mainId)->variant_group_id;
            $this->assertNotNull($group);
            $this->assertSame(['Kırmızı' => '16.0000', 'Mavi' => '15.0000', 'Siyah' => '15.0000'],
                Product::where('variant_group_id', $group)->orderBy('option_value')->pluck('list_price', 'option_value')->map(fn ($p) => (string) $p)->all());
        });
    }

    public function test_server_rejects_invalid_values_and_foreign_records(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        [$foreignCategory, $foreignTax] = $this->inTenant($b, fn () => [Category::create(['name' => 'B kategorisi']), TaxRate::where('rate', '20.00')->value('id')]);
        $tax = $this->inTenant($a, fn () => TaxRate::where('rate', '20.00')->value('id'));
        $this->actingAs($a->user, 'business');

        $this->postJson(route('business.products.folder.store'), $this->product(['tax_rate_id' => $tax, 'list_price' => 'abc']))->assertStatus(422)->assertJsonValidationErrors('list_price');
        $this->postJson(route('business.products.folder.store'), $this->product(['tax_rate_id' => $foreignTax]))->assertStatus(422)->assertJsonValidationErrors('tax_rate_id');
        $this->postJson(route('business.products.folder.store'), $this->product(['tax_rate_id' => $tax, 'category_id' => $foreignCategory->id]))->assertStatus(422)->assertJsonValidationErrors('category_id');
        $this->postJson(route('business.products.folder.store'), $this->product(['tax_rate_id' => $tax, 'images' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]]))->assertStatus(422);
        $this->assertSame(0, $this->inTenant($a, fn () => Product::count()));

        // Başka işletmenin ürün kodu "var" sayılmaz; kendi işletmesinde eklenir.
        $this->inTenant($b, fn () => $this->makeProduct($b, ['sku' => 'ORTAK-1']));
        $this->postJson(route('business.products.folder.lookup'), ['skus' => ['ORTAK-1'], 'barcodes' => []])->assertOk()->assertExactJson(['skus' => [], 'barcodes' => []]);
        $this->postJson(route('business.products.folder.store'), $this->product(['sku' => 'ORTAK-1', 'barcode' => '', 'tax_rate_id' => $tax]))->assertCreated();
    }

    public function test_module_can_be_disabled_by_platform(): void
    {
        $business = $this->makeBusiness();
        BusinessModule::create(['business_id' => $business->id, 'module_key' => 'import', 'enabled' => false]);
        $this->actingAs($business->user, 'business');

        $this->get(route('business.products.folder'))->assertForbidden();
        $this->postJson(route('business.products.folder.store'), $this->product())->assertForbidden();
        $this->get(route('business.products.index'))->assertDontSee('Klasörden ekle');
    }
}
