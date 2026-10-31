<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Vitrin yenilemesi — Aşama 3: ürün kartı, kategori başlığı, indirim filtresi, ürün sayfası teslimat bilgisi. */
class StorefrontProductPageTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreCategory $pens;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'shipping_free_threshold' => '750.00', 'shipping_cargo_fee' => '59.90', 'whatsapp' => '0532 123 45 67']);
        $this->pens = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler', 'tagline' => 'Tükenmez ve jel kalemler', 'color' => 'green']);
        StoreCategory::create(['name' => 'Jel Kalemler', 'slug' => 'jel-kalemler', 'parent_id' => $this->pens->id]);
    }

    private function product(string $name, string $price, int $stock = 10, ?string $compare = null): StoreProduct
    {
        $brand = StoreBrand::firstOrCreate(['slug' => 'pensan'], ['name' => 'Pensan']);
        $product = StoreProduct::create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $this->pens->id, 'brand_id' => $brand->id,
            'is_published' => true, 'description' => "{$name} açıklaması"]);
        $variant = $product->variants()->create(['sku' => strtoupper(Str::slug($name)), 'price' => $price, 'compare_at_price' => $compare, 'vat_rate' => '20']);
        if ($stock) {
            app(StoreStockService::class)->receive($variant, $stock);
        }

        return $product;
    }

    public function test_card_shows_real_discount_and_low_stock_badges(): void
    {
        $this->product('Jel Kalem', '20.00', 3, '25.00');
        $this->product('Tükenmez Kalem', '15.00', 40);
        $this->product('Biten Kalem', '15.00', 0);

        $this->get(self::HOST.'/urunler')->assertOk()
            ->assertSee('%20')->assertSee('Son 3 ürün')->assertSee('Tükendi')
            ->assertDontSee('Son 40 ürün');
    }

    public function test_category_header_and_discount_filter(): void
    {
        $this->product('Jel Kalem', '20.00', 5, '25.00');
        $this->product('Tükenmez Kalem', '15.00');

        $this->get(self::HOST.'/kategori/kalemler')->assertOk()
            ->assertSee('Tükenmez ve jel kalemler')->assertSee('bg-puzzle-green-soft', false)
            ->assertSee('Alt kategoriler')->assertSee('Jel Kalemler')
            ->assertSee('Yalnızca indirimdekiler');

        $this->get(self::HOST.'/urunler?indirim=1')->assertOk()->assertSee('Jel Kalem')->assertDontSee('Tükenmez Kalem');
        $this->get(self::HOST.'/urunler?indirim=abc')->assertSessionHasErrors('indirim');
    }

    public function test_product_page_shows_delivery_summary_and_contact_links(): void
    {
        $product = $this->product('Jel Kalem', '20.00');

        $this->get(self::HOST.'/urun/'.$product->slug)->assertOk()
            ->assertSee('₺750,00')->assertSee('Altında kargo ücreti ₺59,90')
            ->assertDontSee('aynı gün kargoya verilir')           // saat ayarlanmamışsa söz verilmez
            ->assertSee('genellikle <strong>2–3 iş günü</strong> içinde teslim edilir', false)
            ->assertSee('14 gün içinde iade hakkı')
            ->assertSee('https://wa.me/905321234567?text=', false)->assertSee('WhatsApp ile sor')
            ->assertSee('Teslimat ve iade')->assertSee('Jel Kalem açıklaması');

        StoreSetting::put(['cargo_cutoff' => '15:00', 'whatsapp' => null]);
        $this->get(self::HOST.'/urun/'.$product->slug)
            ->assertSee('Hafta içi saat')->assertSee('15:00')
            ->assertDontSee('WhatsApp ile sor');
    }

    public function test_cargo_cutoff_is_validated_and_saved(): void
    {
        $this->actingAs(StoreAdmin::create(['name' => 'V', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
        $base = ['cod_max_open' => 1, 'return_days' => 14, 'iyzico_mode' => 'sandbox', 'sms_driver' => 'log'];

        $this->put(route('store_admin.payments.update'), $base + ['cargo_cutoff' => '25:00'])->assertSessionHasErrors('cargo_cutoff');
        $this->put(route('store_admin.payments.update'), $base + ['cargo_cutoff' => '14:30', 'cargo_delivery_time' => ' 1–2 iş günü '])->assertSessionHasNoErrors();
        $this->assertSame('14:30', StoreSetting::get('cargo_cutoff'));
        $this->assertSame('1–2 iş günü', StoreSetting::get('cargo_delivery_time'));
        $this->put(route('store_admin.payments.update'), $base)->assertSessionHasNoErrors();
        $this->assertNull(StoreSetting::get('cargo_cutoff'));
        $this->assertNull(StoreSetting::get('cargo_delivery_time'));
    }
}
