<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StorePage;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Üst menü sırası, bayi kataloğu görünümündeki Tüm ürünler, Yeni gelenler ve Markalar sayfaları. */
class StorefrontNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreCategory $pens;

    private StoreCategory $gel;

    private StoreCategory $notebooks;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1']);
        $this->pens = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
        $this->gel = StoreCategory::create(['name' => 'Jel Kalemler', 'slug' => 'jel-kalemler', 'parent_id' => $this->pens->id]);
        StoreCategory::create(['name' => 'Mavi Jel', 'slug' => 'mavi-jel', 'parent_id' => $this->gel->id]);
        $this->notebooks = StoreCategory::create(['name' => 'Defterler', 'slug' => 'defterler', 'sort' => 1]);
    }

    private function product(string $name, StoreCategory $category, string $brand = 'Pensan', ?int $daysAgo = 0): StoreProduct
    {
        $brandModel = StoreBrand::firstOrCreate(['slug' => Str::slug($brand)], ['name' => $brand]);
        $product = StoreProduct::create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id, 'brand_id' => $brandModel->id, 'is_published' => true]);
        $product->forceFill(['published_at' => $daysAgo === null ? null : now()->subDays($daysAgo)])->save();
        $variant = $product->variants()->create(['sku' => strtoupper(Str::slug($name)), 'price' => '25.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($variant, 5);

        return $product;
    }

    public function test_header_lists_links_in_order_and_mega_menu_shows_all_levels(): void
    {
        StorePage::create(['key' => 'about', 'is_published' => true, 'content' => ['headline' => 'Biz Kimiz']]);
        StorePage::create(['key' => 'contact', 'is_published' => true, 'content' => []]);

        $html = $this->get(self::HOST.'/')->assertOk()->getContent();
        $nav = Str::between($html, 'Tüm kategoriler', '</nav>');

        $positions = array_map(fn ($label) => mb_strpos($nav, $label), ['Tüm ürünler', 'Markalar', 'Yeni gelenler', 'İndirimler', 'Hakkımızda', 'İletişim']);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);

        // Torun kategoriler de geniş menüde görünür.
        $this->assertStringContainsString('Mavi Jel', $html);
    }

    public function test_category_filter_includes_subcategories(): void
    {
        $this->product('Mavi Jel Kalem', StoreCategory::where('slug', 'mavi-jel')->first());
        $this->product('Çizgili Defter', $this->notebooks);

        $this->get(self::HOST.'/urunler?kategori[]='.$this->pens->id)->assertOk()
            ->assertSee('Mavi Jel Kalem')->assertDontSee('Çizgili Defter')
            ->assertSee('name="kategori[]"', false);

        $this->get(self::HOST.'/urunler?q=defter')->assertOk()->assertSee('Çizgili Defter')->assertDontSee('Mavi Jel Kalem');
    }

    public function test_list_view_renders_rows(): void
    {
        $this->product('Mavi Jel Kalem', $this->gel);

        $this->get(self::HOST.'/urunler?gorunum=liste')->assertOk()->assertSee('Mavi Jel Kalem')->assertSee('MAVI-JEL-KALEM');
        $this->get(self::HOST.'/urunler?gorunum=tablo')->assertRedirect();
    }

    public function test_new_arrivals_use_configured_days_and_fall_back_to_latest(): void
    {
        $this->product('Taze Kalem', $this->gel, daysAgo: 3);
        $this->product('Eski Defter', $this->notebooks, daysAgo: 60);

        $this->get(self::HOST.'/urunler?yeni=1')->assertOk()
            ->assertSee('Yeni Gelenler')->assertSee('Taze Kalem')->assertDontSee('Eski Defter');

        StoreSetting::put(['new_product_days' => '2']);
        $this->get(self::HOST.'/urunler?yeni=1')->assertOk()
            ->assertSee('Son 2 günde yeni ürün eklenmedi')->assertSee('Taze Kalem')->assertSee('Eski Defter');
    }

    public function test_new_product_days_setting_is_validated_and_saved(): void
    {
        $this->actingAs(StoreAdmin::create(['name' => 'Y', 'email' => 'y@magaza.local', 'password' => self::PASSWORD]), 'store_admin');

        $this->put(route('store_admin.settings.update'), ['new_product_days' => '0'])->assertSessionHasErrors('new_product_days');
        $this->put(route('store_admin.settings.update'), ['new_product_days' => '14'])->assertSessionHasNoErrors();
        $this->assertSame('14', StoreSetting::get('new_product_days'));
    }

    public function test_brands_page_shows_featured_and_alphabetical_list_linking_to_filtered_products(): void
    {
        $this->product('Mavi Jel Kalem', $this->gel, 'Pensan');
        $this->product('Çizgili Defter', $this->notebooks, 'Gıpta');
        StoreBrand::create(['name' => 'Boş Marka', 'slug' => 'bos-marka']);

        $brand = StoreBrand::where('slug', 'pensan')->first();
        $this->get(self::HOST.'/markalar')->assertOk()
            ->assertSee('Öne çıkan markalar')->assertSee('Tüm markalar')
            ->assertSee('Pensan')->assertSee('Gıpta')->assertDontSee('Boş Marka')
            ->assertSee('/urunler?marka%5B0%5D='.$brand->id, false);

        $this->get(self::HOST.'/urunler?marka[]='.$brand->id)->assertOk()->assertSee('Mavi Jel Kalem')->assertDontSee('Çizgili Defter');
    }
}
