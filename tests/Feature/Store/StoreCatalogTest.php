<?php

namespace Tests\Feature\Store;

use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mağaza kataloğu (M2): ürün, varyant, görsel, kategori, marka ve iş kuralları. */
class StoreCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Normal yönetici (süper admin değil): katalog işleri tüm yöneticilere açıktır.
        $this->actingAs(StoreAdmin::create(['name' => 'Katalog Yöneticisi', 'email' => 'katalog@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    private function createProduct(array $product = [], array $variant = []): StoreProduct
    {
        $this->post(route('store_admin.products.store'), $product + [
            'name' => 'Spiralli Defter A4', 'description' => 'Kareli', 'is_published' => '0',
            'variant' => $variant + ['sku' => 'DFT-A4', 'price' => '89,90', 'vat_rate' => '20'],
        ])->assertSessionHasNoErrors();

        return StoreProduct::latest('id')->firstOrFail();
    }

    private function image(): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'img');
        $gd = imagecreatetruecolor(400, 300);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 255, 255, 255));
        imagefilledrectangle($gd, 120, 80, 280, 220, imagecolorallocate($gd, 40, 60, 120));
        imagepng($gd, $file);

        return new UploadedFile($file, 'urun.png', 'image/png', null, true);
    }

    public function test_product_is_created_with_its_first_variant_and_turkish_slug(): void
    {
        $category = StoreCategory::create(['name' => 'Defterler', 'slug' => 'defterler']);
        $brand = StoreBrand::create(['name' => 'Gıpta', 'slug' => 'gipta']);

        $product = $this->createProduct(['name' => 'Şık Çizgili Ödev Defteri', 'category_id' => $category->id, 'brand_id' => $brand->id],
            ['compare_at_price' => '109,90', 'desi' => '0,5', 'weight_grams' => '450', 'barcode' => '8690000000100']);

        $this->assertSame('sik-cizgili-odev-defteri', $product->slug);
        $this->assertFalse($product->is_published);
        $variant = $product->variants()->firstOrFail();
        $this->assertSame(['DFT-A4', '89.90', '109.90', '20.00', '0.50', 450], [$variant->sku, $variant->price, $variant->compare_at_price, $variant->vat_rate, $variant->desi, $variant->weight_grams]);
        $this->assertSame(18, $variant->discountPercent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.product_created', 'actor_type' => 'store_admin', 'entity_id' => $product->id]);

        $this->get(route('store_admin.products.index'))->assertOk()->assertSee('Şık Çizgili Ödev Defteri')->assertSee('89,90');
        $this->get(route('store_admin.products.edit', $product))->assertOk()->assertSee('DFT-A4');
    }

    public function test_variant_validation(): void
    {
        $this->createProduct();
        $base = ['name' => 'Kalem', 'variant' => ['sku' => 'KLM-1', 'price' => '10', 'vat_rate' => '20']];

        $cases = [
            'variant.sku' => ['sku' => 'DFT-A4'],                          // başka üründe var
            'variant.price' => ['price' => '0'],
            'variant.compare_at_price' => ['compare_at_price' => '9,99'], // satış fiyatından düşük
            'variant.vat_rate' => ['vat_rate' => '18'],
            'variant.desi' => ['desi' => '-1'],
            'variant.weight_grams' => ['weight_grams' => '1,5'],
        ];
        foreach ($cases as $field => $override) {
            $this->post(route('store_admin.products.store'), ['variant' => $override + $base['variant']] + $base)->assertSessionHasErrors($field);
        }
        $this->assertSame(1, StoreProduct::count());
    }

    public function test_options_flow_and_variant_rules(): void
    {
        $product = $this->createProduct(['name' => 'Jel Kalem'], ['sku' => 'JEL-MAVI', 'price' => '24,90']);
        $form = fn (array $o = []) => $o + ['name' => 'Jel Kalem', 'is_published' => '0'];

        // Seçenek adı verilirken mevcut varyantın değeri zorunlu.
        $this->put(route('store_admin.products.update', $product), $form(['variant_name' => 'Renk']))->assertSessionHasErrors('first_option_value');
        $this->put(route('store_admin.products.update', $product), $form(['variant_name' => 'Renk', 'first_option_value' => 'Mavi']))->assertSessionHasNoErrors();
        $this->assertSame('Mavi', $product->variants()->value('option_value'));

        $new = ['sku' => 'JEL-SIYAH', 'option_value' => 'Siyah', 'price' => '24,90', 'vat_rate' => '20'];
        $this->post(route('store_admin.variants.store', $product), $new)->assertSessionHasNoErrors();
        $this->post(route('store_admin.variants.store', $product), ['sku' => 'JEL-X', 'option_value' => 'siyah'] + $new)->assertSessionHasErrors('option_value', null, 'newVariant');
        $this->assertSame(2, $product->variants()->count());

        // Çok seçenekli ürünün seçenek adı kaldırılamaz.
        $this->put(route('store_admin.products.update', $product), $form(['variant_name' => '']))->assertSessionHasErrors('product');

        // Yayındaki ürünün son etkin seçeneği pasif yapılamaz / silinemez.
        $this->put(route('store_admin.products.update', $product), $form(['variant_name' => 'Renk', 'is_published' => '1']))->assertSessionHasNoErrors();
        [$mavi, $siyah] = $product->variants()->get()->all();
        $this->put(route('store_admin.variants.update', $siyah), ['sku' => 'JEL-SIYAH', 'option_value' => 'Siyah', 'price' => '24,90', 'vat_rate' => '20', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->put(route('store_admin.variants.update', $mavi), ['sku' => 'JEL-MAVI', 'option_value' => 'Mavi', 'price' => '24,90', 'vat_rate' => '20', 'is_active' => '0'])->assertSessionHasErrors('product');
        $this->delete(route('store_admin.variants.destroy', $mavi))->assertSessionHasErrors('product');

        $this->delete(route('store_admin.variants.destroy', $siyah))->assertSessionHasNoErrors();
        $this->delete(route('store_admin.variants.destroy', $mavi))->assertSessionHasErrors('product'); // son varyant
        $this->assertTrue(StoreProduct::findOrFail($product->id)->published_at !== null);
    }

    public function test_images_are_processed_ordered_and_removed(): void
    {
        $product = $this->createProduct();

        $this->post(route('store_admin.images.store', $product), ['images' => [$this->image(), $this->image()]])->assertSessionHasNoErrors();
        [$first, $second] = $product->images()->get()->all();
        Storage::disk('local')->assertExists($first->path);
        $this->assertStringStartsWith("store/products/{$product->id}/", $first->path);
        $this->assertLessThan(400, getimagesizefromstring(Storage::disk('local')->get($first->path))[0], 'beyaz kenar kırpılır');

        $this->get(route('store_admin.images.show', $first))->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->put(route('store_admin.images.primary', $second))->assertSessionHasNoErrors();
        $this->assertSame($second->id, $product->fresh()->primaryImage->id);

        $this->delete(route('store_admin.images.destroy', $second))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($second->path);

        $this->delete(route('store_admin.products.destroy', $product))->assertRedirect(route('store_admin.products.index'));
        Storage::disk('local')->assertMissing($first->path);
        $this->assertSame(0, StoreProductVariant::count());
    }

    public function test_category_tree_rules(): void
    {
        $this->post(route('store_admin.categories.store'), ['name' => 'Kırtasiye'])->assertSessionHasNoErrors();
        $root = StoreCategory::firstOrFail();
        $this->assertSame('kirtasiye', $root->slug);

        $this->post(route('store_admin.categories.store'), ['name' => 'Kalemler', 'parent_id' => $root->id]);
        $child = StoreCategory::where('name', 'Kalemler')->firstOrFail();
        $this->post(route('store_admin.categories.store'), ['name' => 'Jel Kalem', 'parent_id' => $child->id])->assertSessionHasNoErrors();
        $leaf = StoreCategory::where('name', 'Jel Kalem')->firstOrFail();
        $this->assertSame('Kırtasiye › Kalemler › Jel Kalem', $leaf->fullName());

        // 4. seviye ve döngü engellenir
        $this->post(route('store_admin.categories.store'), ['name' => 'Fazla', 'parent_id' => $leaf->id])->assertSessionHasErrors('parent_id');
        $this->put(route('store_admin.categories.update', $root), ['name' => 'Kırtasiye', 'parent_id' => $leaf->id, 'is_active' => '1'])->assertSessionHasErrors('parent_id');

        // Ürünü veya alt kategorisi olan silinemez
        $this->createProduct(['category_id' => $leaf->id]);
        $this->delete(route('store_admin.categories.destroy', $child))->assertSessionHasErrors('category');
        $this->delete(route('store_admin.categories.destroy', $leaf))->assertSessionHasErrors('category');

        $this->get(route('store_admin.categories.index'))->assertOk()->assertSee('Jel Kalem');
        $this->get(route('store_admin.products.index', ['category' => $root->id]))->assertOk()->assertSee('Spiralli Defter A4');
    }

    public function test_brands_and_logos(): void
    {
        $this->post(route('store_admin.brands.store'), ['name' => 'Faber-Castell'])->assertSessionHasNoErrors();
        $this->post(route('store_admin.brands.store'), ['name' => 'Faber-Castell'])->assertSessionHasErrors('name');
        $brand = StoreBrand::firstOrFail();

        $this->post(route('store_admin.brands.logo', $brand), ['logo' => $this->image()])->assertSessionHasNoErrors();
        $this->get(route('store_admin.brands.logo.show', $brand))->assertOk();

        $this->createProduct(['brand_id' => $brand->id]);
        $this->delete(route('store_admin.brands.destroy', $brand))->assertSessionHasErrors('brand');
        $this->get(route('store_admin.brands.index'))->assertOk()->assertSee('Faber-Castell');
    }

    public function test_bulk_publish_skips_products_without_active_variant(): void
    {
        $a = $this->createProduct([], ['sku' => 'A-1']);
        $b = $this->createProduct(['name' => 'İkinci'], ['sku' => 'B-1']);
        $b->variants()->update(['is_active' => false]);

        $this->post(route('store_admin.products.bulk'), ['ids' => [$a->id, $b->id], 'action' => 'publish'])->assertSessionHas('status', '1 ürün yayına alındı.');
        $this->assertTrue($a->fresh()->is_published);
        $this->assertFalse($b->fresh()->is_published);
    }

    public function test_catalog_is_not_reachable_from_b2b_sessions(): void
    {
        $product = $this->createProduct();
        auth('store_admin')->logout();
        $this->actingAs($this->makeBusiness()->user, 'business');

        $this->get(route('store_admin.products.index'))->assertRedirect(route('store_admin.login'));
        $this->get(route('store_admin.products.edit', $product))->assertRedirect(route('store_admin.login'));
    }
}
