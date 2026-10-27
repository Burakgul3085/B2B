<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBanner;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreCollection;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StorePage;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Vitrin yenilemesi — Aşama 2: ana sayfa blokları, kupon kartları, sekmeli vitrin, seçkiler, yönetimden sıralama. */
class StorefrontHomeTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreCategory $pens;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        StoreSetting::put(['is_open' => '1']);
        $this->pens = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler', 'tagline' => 'Tükenmez ve jel kalemler']);
        $this->actingAs(StoreAdmin::create(['name' => 'V', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    private function product(string $name, string $price, ?string $compareAt = null, bool $featured = false): StoreProduct
    {
        $product = StoreProduct::create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $this->pens->id, 'is_published' => true, 'is_featured' => $featured]);
        $variant = $product->variants()->create(['sku' => strtoupper(Str::slug($name)), 'price' => $price, 'compare_at_price' => $compareAt, 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($variant, 10);

        return $product;
    }

    public function test_empty_store_shows_brand_hero_and_hides_empty_blocks(): void
    {
        $this->get(self::HOST.'/')->assertOk()
            ->assertSee('her parça')->assertSee('Ürünlere göz at')          // banner yokken açılış alanı
            ->assertSee('Hızlı teslimat')                                  // avantaj şeridi
            ->assertDontSee('Sepetiniz için hazır avantajlar')             // kupon yok
            ->assertDontSee('Bugün neye ihtiyacınız var?')                 // seçki yok
            ->assertSee('Kırtasiyede aradığınız her şey');                 // kategori var
    }

    public function test_showcase_tabs_coupons_collections_and_about_use_real_data(): void
    {
        $this->product('Jel Kalem', '20.00', featured: true);
        $this->product('İndirimli Defter', '40.00', '60.00');
        StoreDiscount::create(['name' => 'Hoş geldin', 'code' => 'HOSGELDIN', 'type' => 'percent', 'value' => '10', 'is_active' => true, 'show_on_home' => true]);
        StoreDiscount::create(['name' => 'Gizli', 'code' => 'GIZLI', 'type' => 'percent', 'value' => '50', 'is_active' => true]);
        StoreDiscount::create(['name' => 'Bitti', 'code' => 'BITTI', 'type' => 'fixed', 'value' => '5', 'is_active' => true, 'show_on_home' => true, 'ends_at' => now()->subDay()]);
        StorePage::create(['key' => 'about', 'is_published' => true, 'content' => ['headline' => 'Biz Kimiz', 'stats' => [['value' => '1998', 'label' => 'Kuruluş']]]]);

        $this->post(route('store_admin.collections.store'), ['title' => 'Okula dönüş', 'product_skus' => "YOK-1\nJEL-KALEM"])->assertSessionHasErrors('product_skus');
        $this->post(route('store_admin.collections.store'), ['title' => 'Okula dönüş', 'subtitle' => 'Yeni sezon', 'color' => 'blue', 'product_skus' => "INDIRIMLI-DEFTER\njel-kalem", 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $collection = StoreCollection::sole();
        $this->assertSame(['indirimli-defter', 'jel-kalem'], $collection->products()->pluck('slug')->all(), 'yazılan sırayla');

        $this->get(self::HOST.'/')->assertOk()
            ->assertSee('Öne çıkanlar')->assertSee('Yeni gelenler')->assertSee('İndirimdekiler')->assertDontSee('Çok satanlar') // satış yok
            ->assertSee('HOSGELDIN')->assertSee('%10')->assertDontSee('GIZLI')->assertDontSee('BITTI')
            ->assertSee('Okula dönüş')->assertSee('2 ürün')
            ->assertSee('Biz Kimiz')->assertSee('1998');

        $this->get(self::HOST.'/secki/'.$collection->slug)->assertOk()->assertSee('Okula dönüş')->assertSeeInOrder(['İndirimli Defter', 'Jel Kalem']);
        $collection->update(['is_active' => false]);
        $this->get(self::HOST.'/secki/'.$collection->slug)->assertNotFound();
    }

    public function test_dealer_invite_links_to_b2b_application_and_login_and_can_be_hidden(): void
    {
        $b2b = rtrim(config('app.url'), '/');
        $html = $this->get(self::HOST.'/')->assertOk()
            ->assertSee('id="ana-bayilik"', false)->assertSee('Bayimiz olmak ister misiniz?')->assertSee('Bayi paneline giriş')
            ->assertSee('href="'.$b2b.'/basvuru"', false)->assertSee('href="'.$b2b.'/bayi/login"', false)
            ->assertSee('class="dealer-mark"', false)
            ->getContent();
        // Varsayılan sırada son blok: alt bilginin hemen üstünde
        $this->assertLessThan(strpos($html, '<footer'), strpos($html, 'id="ana-bayilik"'));

        // Başlık değiştirilebilir (vurgu yalnızca varsayılan ifadede); blok kapatılabilir
        $this->put(route('store_admin.home-layout.update'), ['blocks' => ['dealer' => ['enabled' => '1', 'title' => 'Bayimiz olun <b>']]])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/')->assertSee('Bayimiz olun &lt;b&gt;', false)->assertDontSee('class="dealer-mark"', false);
        $this->put(route('store_admin.home-layout.update'), ['blocks' => ['dealer' => ['enabled' => '0']]])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/')->assertOk()->assertDontSee('id="ana-bayilik"', false);
    }

    public function test_admin_orders_and_hides_blocks_and_places_side_banners(): void
    {
        $this->get(route('store_admin.home-layout.edit'))->assertOk()->assertSee('Kategori kutuları');
        $this->put(route('store_admin.home-layout.update'), ['blocks' => [
            'hero' => ['enabled' => '1', 'sort' => 5], 'perks' => ['enabled' => '0', 'sort' => 2], 'categories' => ['enabled' => '1', 'sort' => 1, 'title' => 'Raflarımız'],
            'coupons' => ['enabled' => '1'], 'showcase' => ['enabled' => '1'], 'collections' => ['enabled' => '1'], 'brands' => ['enabled' => '1'], 'about' => ['enabled' => '1'],
        ]])->assertSessionHasNoErrors();

        $html = $this->get(self::HOST.'/')->assertOk()->assertSee('Raflarımız')->assertDontSee('aria-label="Avantajlar"', false)->getContent();
        $this->assertLessThan(strpos($html, 'her parça'), strpos($html, 'Raflarımız'), 'kategoriler banner alanından önce');

        $banner = new StoreBanner(['title' => 'Yan kutu', 'placement' => 'side']);
        $banner->image_path = 'store/banners/x.webp';
        $banner->save();
        // Büyük alan banner'ı yokken açılış alanı gösterilir; kampanya kutusu altında yer alır.
        $html = $this->get(self::HOST.'/')->assertSee('Yan kutu')->assertSee('her parça')->getContent();
        $this->assertLessThan(strpos($html, 'Yan kutu'), strpos($html, 'her parça'));
        $this->assertSame('side', StoreBanner::sole()->placement);
    }

    public function test_discount_home_flag_only_for_public_coupons(): void
    {
        $this->post(route('store_admin.discounts.store'), ['name' => 'Kampanya', 'kind' => 'campaign', 'type' => 'percent', 'value' => '10', 'is_active' => '1', 'show_on_home' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertFalse(StoreDiscount::sole()->show_on_home, 'kodsuz kampanya kart olmaz');
    }
}
