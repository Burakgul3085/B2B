<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Inventory\StoreStockService;
use App\Http\Middleware\EnsureStoreOpen;
use App\Models\Store\StoreBanner;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StorePage;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Mağaza vitrini (M4): yayın kapısı, önizleme, listeler, filtreler, ürün sayfası, görseller ve SEO. */
class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreCategory $pens;

    private StoreBrand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        StoreSetting::put(['is_open' => '1']);

        $root = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
        $this->pens = StoreCategory::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'parent_id' => $root->id]);
        $this->brand = StoreBrand::create(['name' => 'Pensan', 'slug' => 'pensan']);
    }

    private function product(string $name, string $price, int $stock = 10, array $attrs = []): StoreProduct
    {
        $product = StoreProduct::create($attrs + ['name' => $name, 'slug' => Str::slug($name), 'category_id' => $this->pens->id,
            'brand_id' => $this->brand->id, 'is_published' => true]);
        $product->forceFill(['published_at' => now()])->save();
        $variant = $product->variants()->create(['sku' => strtoupper(Str::slug($name)), 'price' => $price, 'vat_rate' => '20']);
        if ($stock) {
            app(StoreStockService::class)->receive($variant, $stock);
        }

        return $product;
    }

    public function test_closed_store_shows_coming_soon_unless_previewing(): void
    {
        StoreSetting::put(['is_open' => '0']);
        $this->product('Jel Kalem Mavi', '24.90');

        $this->get(self::HOST.'/')->assertStatus(503)->assertSee('Online mağazamız çok yakında açılıyor');
        $this->get(self::HOST.'/urun/jel-kalem-mavi')->assertStatus(503);
        $this->get(self::HOST.'/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get(self::HOST.'/sitemap.xml')->assertNotFound();

        // İmzasız önizleme bağlantısı geçersiz; imzalı bağlantı çerez verir.
        $this->get(self::HOST.'/onizleme')->assertForbidden();
        $this->get(URL::temporarySignedRoute('store.preview', now()->addMinutes(5)))
            ->assertRedirect(route('store.home'))->assertCookie(EnsureStoreOpen::PREVIEW_COOKIE, '1');

        $this->withCookie(EnsureStoreOpen::PREVIEW_COOKIE, '1')->get(self::HOST.'/')
            ->assertOk()->assertSee('Önizleme modu')->assertSee('noindex, follow', false);
    }

    public function test_only_published_products_with_active_variants_are_listed(): void
    {
        $this->product('Jel Kalem Mavi', '24.90');
        $this->product('Taslak Kalem', '10.00', attrs: ['is_published' => false]);
        $passive = $this->product('Satışta Olmayan Kalem', '12.00');
        $passive->variants()->update(['is_active' => false]);

        $this->get(self::HOST.'/urunler')->assertOk()->assertSee('Jel Kalem Mavi')->assertDontSee('Taslak Kalem')->assertDontSee('Satışta Olmayan Kalem');
        $this->get(self::HOST.'/urun/taslak-kalem')->assertNotFound();
        $this->get(self::HOST.'/urun/satista-olmayan-kalem')->assertNotFound();
    }

    public function test_category_page_includes_subcategories_and_hides_inactive_categories(): void
    {
        $this->product('Jel Kalem Mavi', '24.90');

        $this->get(self::HOST.'/kategori/kalemler')->assertOk()->assertSee('Jel Kalem Mavi')->assertSee('Jel Kalem')
            ->assertSee('"@type":"BreadcrumbList"', false);
        $this->get(self::HOST.'/kategori/jel-kalem')->assertOk()->assertSee('Kalemler')->assertSee('Jel Kalem Mavi');

        StoreCategory::where('slug', 'kalemler')->update(['is_active' => false]);
        $this->get(self::HOST.'/kategori/kalemler')->assertNotFound();
        $this->get(self::HOST.'/kategori/jel-kalem')->assertNotFound(); // üst kategorisi gizliyse alt kategori de açılmaz
    }

    public function test_filters_sorting_and_search(): void
    {
        $cheap = $this->product('Ucuz Kalem', '5.00');
        $this->product('Pahalı Kalem', '50.00', stock: 0);
        $other = StoreBrand::create(['name' => 'Faber-Castell', 'slug' => 'faber']);
        $this->product('Faber Kurşun Kalem', '15.00', attrs: ['brand_id' => $other->id]);

        $names = fn ($response) => $response->viewData('products')->pluck('name')->all();

        $this->assertSame(['Ucuz Kalem', 'Faber Kurşun Kalem', 'Pahalı Kalem'], $names($this->get(self::HOST.'/urunler?sirala=fiyat-artan')));
        $this->assertSame(['Faber Kurşun Kalem'], $names($this->get(self::HOST."/urunler?marka[]={$other->id}")));
        $this->assertSame(['Faber Kurşun Kalem', 'Pahalı Kalem'], $names($this->get(self::HOST.'/urunler?min=10&sirala=fiyat-artan')));
        $this->assertSame(['Ucuz Kalem', 'Faber Kurşun Kalem'], $names($this->get(self::HOST.'/urunler?stokta=1&sirala=fiyat-artan')));

        $this->assertSame(['Faber Kurşun Kalem'], $names($this->get(self::HOST.'/ara?q=faber kurşun')));
        $this->assertSame(['Ucuz Kalem'], $names($this->get(self::HOST.'/ara?q='.$cheap->variants()->value('sku'))));
        $this->get(self::HOST.'/ara?q=bulunmayan')->assertOk()->assertSee('uygun ürün bulunamadı')->assertSee('noindex, follow', false);

        // Filtreli liste dizine eklenmez, filtresiz liste eklenir.
        $this->get(self::HOST.'/urunler?stokta=1')->assertSee('noindex, follow', false);
        $this->get(self::HOST.'/urunler')->assertSee('index, follow', false);
    }

    public function test_product_page_shows_options_stock_and_structured_data(): void
    {
        $product = StoreProduct::create(['name' => 'Jel Kalem 0.7', 'slug' => 'jel-kalem-07', 'category_id' => $this->pens->id, 'brand_id' => $this->brand->id,
            'variant_name' => 'Renk', 'is_published' => true, 'description' => 'Hızlı kuruyan mürekkep.']);
        $blue = $product->variants()->create(['sku' => 'J-M', 'option_value' => 'Mavi', 'price' => '24.90', 'compare_at_price' => '29.90', 'vat_rate' => '20']);
        $product->variants()->create(['sku' => 'J-S', 'option_value' => 'Siyah', 'price' => '26.90', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($blue, 3);

        $response = $this->get(self::HOST.'/urun/jel-kalem-07')->assertOk()
            ->assertSee('Jel Kalem 0.7')->assertSee('Mavi')->assertSee('Siyah')->assertSee('Hızlı kuruyan mürekkep.')
            ->assertSee('"@type":"Product"', false)->assertSee('"@type":"AggregateOffer"', false)
            ->assertSee('"availability":"https://schema.org/OutOfStock"', false)
            ->assertSee('<link rel="canonical" href="http://magaza.localhost/urun/jel-kalem-07">', false);
        // İlk seçilen seçenek stokta olan (Mavi) ve fiyatı
        $response->assertSee('₺24,90');
        $this->assertSame([$blue->id => 3, $blue->id + 1 => 0], $response->viewData('available'));
    }

    public function test_images_are_served_only_for_published_products_in_requested_size(): void
    {
        $product = $this->product('Görselli Kalem', '9.90');
        $gd = imagecreatetruecolor(1600, 1200);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 30, 60, 120));
        ob_start();
        imagewebp($gd);
        Storage::disk('local')->put("store/products/{$product->id}/a.webp", ob_get_clean());
        $image = $product->images()->create(['path' => "store/products/{$product->id}/a.webp"]);

        $this->get(self::HOST."/gorsel/urun/{$image->id}?w=640")->assertOk()->assertHeader('Cache-Control', 'max-age=604800, public');
        Storage::disk('local')->assertExists("store/products/{$product->id}/w640/a.webp");
        $this->assertSame(640, getimagesizefromstring(Storage::disk('local')->get("store/products/{$product->id}/w640/a.webp"))[0]);

        $product->update(['is_published' => false]);
        $this->get(self::HOST."/gorsel/urun/{$image->id}")->assertNotFound();
    }

    public function test_home_brands_pages_and_banners(): void
    {
        $this->product('Öne Çıkan Kalem', '19.90', attrs: ['is_featured' => true]);
        StorePage::create(['title' => 'Hakkımızda', 'slug' => 'hakkimizda', 'body' => "## Biz kimiz\n\n**Yapboz** Eskişehir'de.\n\n<script>alert(1)</script>"]);
        StorePage::create(['title' => 'Gizli', 'slug' => 'gizli', 'is_active' => false]);
        Storage::disk('local')->put('store/banners/b.webp', 'x');
        $banner = new StoreBanner(['title' => 'Okula Dönüş', 'link_url' => '/kategori/kalemler']);
        $banner->image_path = 'store/banners/b.webp';
        $banner->save();
        $expired = new StoreBanner(['title' => 'Bitmiş Kampanya', 'ends_at' => now()->subDay()]);
        $expired->image_path = 'store/banners/b.webp';
        $expired->save();

        $this->get(self::HOST.'/')->assertOk()->assertSee('Öne çıkan ürünler')->assertSee('Öne Çıkan Kalem')->assertSee('Okula Dönüş')
            ->assertDontSee('Bitmiş Kampanya')->assertSee('Hakkımızda')->assertDontSee('/sayfa/gizli')->assertSee('"@type":"WebSite"', false);

        $this->get(self::HOST.'/sayfa/hakkimizda')->assertOk()->assertSee('<h2>Biz kimiz</h2>', false)->assertSee('<strong>Yapboz</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get(self::HOST.'/sayfa/gizli')->assertNotFound();
        $this->get(self::HOST.'/markalar')->assertOk()->assertSee('Pensan');
        $this->get(self::HOST.'/marka/pensan')->assertOk()->assertSee('Öne Çıkan Kalem');
    }

    public function test_robots_and_sitemap_when_open(): void
    {
        $this->product('Jel Kalem Mavi', '24.90');
        $this->product('Taslak', '1.00', attrs: ['is_published' => false]);

        $this->get(self::HOST.'/robots.txt')->assertOk()->assertSee('Allow: /')->assertSee('Sitemap: http://magaza.localhost/sitemap.xml');
        $this->get(self::HOST.'/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('http://magaza.localhost/urun/jel-kalem-mavi')->assertSee('http://magaza.localhost/kategori/jel-kalem')->assertDontSee('/urun/taslak');

        // B2B ve mağaza yönetimi her zaman kapalı
        $this->get('http://localhost/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get('http://yonetim.localhost/robots.txt')->assertOk()->assertSee('Disallow: /');
    }

    public function test_storefront_follows_the_theme_cookie(): void
    {
        $this->get(self::HOST.'/')->assertOk()->assertSee('<html lang="tr" class="">', false)->assertSee('x-data="themeSwitch"', false);
        $this->withUnencryptedCookie('tema', 'dark')->get(self::HOST.'/')->assertSee('<html lang="tr" class="dark">', false);
        $this->withUnencryptedCookie('tema', 'auto')->get(self::HOST.'/')->assertDontSee('class="dark"', false); // sistem: tarayıcıda uygulanır
        $this->withUnencryptedCookie('tema', '"><script>')->get(self::HOST.'/')->assertSee('<html lang="tr" class="">', false);

        // Çerez politikası tema tercih çerezini listeler
        $this->get(self::HOST.'/yasal/gizlilik-cerez')->assertOk()->assertSee('Seçtiğiniz görünümün (açık/koyu) hatırlanması');

        StoreSetting::put(['is_open' => '0']);
        $this->withUnencryptedCookie('tema', 'dark')->get(self::HOST.'/')->assertStatus(503)->assertSee('<html lang="tr" class="dark">', false);
    }
}
