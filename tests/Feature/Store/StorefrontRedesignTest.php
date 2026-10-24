<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StorePage;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Vitrin yenilemesi — Aşama 1: üst şerit, geniş menü, kategori renkleri, canlı arama, mini sepet. */
class StorefrontRedesignTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreCategory $pens;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        StoreSetting::put(['is_open' => '1', 'shipping_free_threshold' => '750.00', 'phone' => '0222 123 45 67', 'whatsapp' => '0532 123 45 67']);
        $this->pens = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler', 'tagline' => 'Tükenmez ve jel kalemler', 'color' => 'green']);
        StoreCategory::create(['name' => 'Jel Kalemler', 'slug' => 'jel-kalemler', 'parent_id' => $this->pens->id]);
        StoreCategory::create(['name' => 'Defterler', 'slug' => 'defterler', 'sort' => 1]);
    }

    private function product(string $name, string $price, int $stock = 10): StoreProduct
    {
        $brand = StoreBrand::firstOrCreate(['slug' => 'pensan'], ['name' => 'Pensan']);
        $product = StoreProduct::create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $this->pens->id, 'brand_id' => $brand->id, 'is_published' => true]);
        $variant = $product->variants()->create(['sku' => strtoupper(Str::slug($name)), 'price' => $price, 'vat_rate' => '20']);
        if ($stock) {
            app(StoreStockService::class)->receive($variant, $stock);
        }

        return $product;
    }

    public function test_header_mega_menu_and_category_colours(): void
    {
        $this->get(self::HOST.'/')->assertOk()
            ->assertSee('Tüm kategoriler')->assertSee('Tükenmez ve jel kalemler')->assertSee('Jel Kalemler')
            ->assertSee('bg-puzzle-green', false)   // seçilen renk
            ->assertSee('https://wa.me/905321234567', false)   // mobil çekmecede WhatsApp
            ->assertDontSee('Sipariş takibi')                   // lacivert üst şerit kaldırıldı
            ->assertSee('Hızlı menü');                          // mobil alt menü

        // Duyuru yazılmışsa ince bir şerit olarak görünür.
        StoreSetting::put(['announcement' => 'Okula dönüş fırsatları başladı!']);
        $this->get(self::HOST.'/')->assertSee('Okula dönüş fırsatları başladı!');
    }

    public function test_favorites_are_shown_only_to_members(): void
    {
        $this->product('Jel Kalem', '20.00');
        $this->get(self::HOST.'/urunler')->assertDontSee('aria-label="Favorilerim"', false)->assertDontSee('favorilere ekle');

        $customer = StoreCustomer::create(['first_name' => 'Ayşe', 'last_name' => 'Kaya', 'email' => 'ayse@ornek.com', 'password' => self::PASSWORD]);
        $this->actingAs($customer, 'customer');
        $this->get(self::HOST.'/urunler')->assertSee('aria-label="Favorilerim"', false)->assertSee('Jel Kalem favorilere ekle');
    }

    public function test_category_colour_tagline_and_image_are_managed(): void
    {
        $this->actingAs(StoreAdmin::create(['name' => 'V', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
        $file = tempnam(sys_get_temp_dir(), 'cat');
        $gd = imagecreatetruecolor(900, 900);
        imagepng($gd, $file);

        $this->put(route('store_admin.categories.update', $this->pens), ['name' => 'Kalemler', 'color' => 'mor'])->assertSessionHasErrors('color');
        $this->put(route('store_admin.categories.update', $this->pens), ['name' => 'Kalemler', 'color' => 'orange', 'tagline' => 'Her türlü kalem', 'is_active' => '1',
            'image' => new UploadedFile($file, 'k.png', 'image/png', null, true)])->assertSessionHasNoErrors();
        $this->pens->refresh();
        $this->assertSame(['orange', 'Her türlü kalem'], [$this->pens->color, $this->pens->tagline]);
        Storage::disk('local')->assertExists($this->pens->image_path);
        $this->get(route('store_admin.categories.image', $this->pens))->assertOk();
        $this->get(self::HOST.'/gorsel/kategori/'.$this->pens->id)->assertOk();
        $this->get(self::HOST.'/')->assertSee('/gorsel/kategori/'.$this->pens->id, false);

        $this->put(route('store_admin.categories.update', $this->pens), ['name' => 'Kalemler', 'remove_image' => '1', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($this->pens->fresh()->image_path);
    }

    public function test_live_search_suggests_products_categories_and_brands(): void
    {
        $this->product('Pensan Jel Kalem Mavi', '24.90');
        $this->product('Gizli Kalem', '10.00')->update(['is_published' => false]);

        $this->getJson(self::HOST.'/ara/oneri?q=k')->assertOk()->assertExactJson(['products' => [], 'categories' => [], 'brands' => []]);
        $json = $this->getJson(self::HOST.'/ara/oneri?q=kalem')->assertOk()->json();
        $this->assertSame(['Pensan Jel Kalem Mavi'], array_column($json['products'], 'name'));
        $this->assertSame('₺24,90', $json['products'][0]['price']);
        $this->assertSame(['Kalemler', 'Jel Kalemler'], array_column($json['categories'], 'name'));
        $this->assertSame([], $this->getJson(self::HOST.'/ara/oneri?q=pensan')->json('categories'));
        $this->assertSame(['Pensan'], array_column($this->getJson(self::HOST.'/ara/oneri?q=pensan')->json('brands'), 'name'));
    }

    public function test_add_to_cart_returns_json_and_mini_cart_shows_free_shipping_progress(): void
    {
        $variant = $this->product('Pensan Jel Kalem Mavi', '250.00', 3)->variants()->first();

        $added = $this->postJson(self::HOST.'/sepet', ['variant_id' => $variant->id, 'quantity' => 2])->assertOk()->assertJson(['count' => 2]);
        $this->withCookie('magaza_sepet', $added->getCookie('magaza_sepet')->getValue());
        $this->postJson(self::HOST.'/sepet', ['variant_id' => $variant->id, 'quantity' => 5])->assertStatus(422)->assertJsonStructure(['message']);

        $this->get(self::HOST.'/sepet/cekmece')->assertOk()
            ->assertSee('Pensan Jel Kalem Mavi')->assertSee('2 × ₺250,00')->assertSee('Ücretsiz kargo için')->assertSee('₺250,00')->assertSee('Siparişi tamamla');

        // JavaScript olmadan normal form gibi döner.
        $this->post(self::HOST.'/sepet', ['variant_id' => $variant->id])->assertRedirect()->assertSessionHas('cart_added');
    }

    public function test_footer_shows_payment_marks_and_credits(): void
    {
        $this->get(self::HOST.'/')->assertOk()
            ->assertSee('aria-label="Ödeme yöntemleri"', false)
            ->assertSee('iyzico ile öde')->assertSee('aria-label="Visa"', false)->assertSee('aria-label="Mastercard"', false)
            ->assertSee('aria-label="American Express"', false)->assertSee('aria-label="Troy"', false)
            ->assertDontSee('Havale / EFT</li>', false)
            ->assertDontSee('Fiyatlara KDV dahildir')
            ->assertSee('Sayfa yöneticisi')->assertSee('Onur Birbilen')
            ->assertSee('Burak Gül')->assertSee('mailto:burakgul3085@gmail.com', false)->assertSee('https://www.linkedin.com/in/burakgul1006/', false);

        StoreSetting::put(['transfer_enabled' => '1']);
        $this->get(self::HOST.'/')->assertSee('Havale / EFT</li>', false);
    }

    public function test_marquee_is_managed_and_lists_installment_cards(): void
    {
        // Varsayılan maddeler ayarlardan: ücretsiz kargo sınırı (setUp: ₺750)
        $this->get(self::HOST.'/')->assertSee('aria-label="Kayan duyurular"', false)->assertSee('₺750,00 üzeri alışverişlerde')->assertSee('Kargo bedava')
            ->assertSee('aria-label="Bonus"', false)->assertSee('aria-label="CardFinans"', false);

        $this->actingAs(StoreAdmin::create(['name' => 'V', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
        // Form, ayarlardan hazırlanmış önerilerle dolu gelir (düzenlenip kaydedilir)
        $this->get(route('store_admin.footer.edit'))->assertOk()->assertSee('value="Faturalı satış"', false)->assertSee('value="Kargo bedava"', false);
        $base = ['shop_title' => 'Alışveriş', 'category_count' => 6, 'info_title' => 'Bilgi', 'store_title' => 'Mağazamız'];
        $this->put(route('store_admin.footer.update'), $base + ['marquee_enabled' => '1', 'marquee_color' => 'mor'])->assertSessionHasErrors('marquee_color');
        $this->put(route('store_admin.footer.update'), $base + ['marquee_enabled' => '1', 'marquee_color' => 'navy', 'marquee_speed' => 'fast',
            'marquee_items' => [['icon' => 'building', 'text' => "1989'dan beri", 'badge' => '37 yıl güveni'], ['icon' => 'star', 'text' => '', 'badge' => '']]])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/')->assertSee('1989&#039;dan beri', false)->assertSee('37 yıl güveni')->assertDontSee('üzeri alışverişlerde')->assertSee('bg-navy text-white', false);

        $this->put(route('store_admin.footer.update'), $base + ['marquee_enabled' => '0'])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/')->assertDontSee('aria-label="Kayan duyurular"', false);
    }

    public function test_footer_shows_store_map_to_everyone_without_address(): void
    {
        StorePage::create(['key' => 'contact', 'is_published' => true, 'content' => [
            'map_embed' => 'https://www.google.com/maps?q=39.77,30.52&z=17&hl=tr&output=embed',
            'map_directions' => 'https://www.google.com/maps/dir/?api=1&destination=39.77,30.52',
        ]]);

        $this->get(self::HOST.'/urunler')->assertOk()
            ->assertSee('<iframe src="https://www.google.com/maps?q=39.77,30.52&amp;z=17&amp;hl=tr&amp;output=embed"', false)
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=39.77,30.52', false)
            ->assertSee('Yol tarifi');
    }
}
