<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Shipping\CarrierBrands;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kargo firması seçimi ve takibi: müşteri ücretli firmalardan birini seçer (ücret sunucuda firmadan alınır), ücretsiz
 * kargo sınırında seçmez; firma logosu ve takip bağlantısı addan gelir; kargoya verilince e-posta ve "Kargom nerede".
 */
class StoreCarrierChoiceTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    /** @var list<array{to: string, subject: string, content: array}> */
    public array $mails = [];

    private StoreCart $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $test = $this;
        $this->instance(StoreMailer::class, new class($test) extends StoreMailer
        {
            public function __construct(private $test) {}

            public function send(string $to, string $subject, array $content): bool
            {
                $this->test->mails[] = compact('to', 'subject', 'content');

                return true;
            }
        });
        StoreSetting::put(['is_open' => '1', 'transfer_enabled' => '1', 'transfer_days' => '3', 'shipping_cargo_fee' => '30.00',
            'transfer_accounts' => 'Ziraat Bankası | Yapboz Kırtasiye | TR330006100519786457841326']);

        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($pen, 10);
        $this->cart = app(CartService::class)->createGuest();
        app(CartService::class)->add($this->cart, $pen->id, 2);
        $this->withCookie(CurrentCart::COOKIE, $this->cart->token);
    }

    private function form(array $override = []): array
    {
        return array_replace_recursive([
            'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567', 'province_id' => 6,
                'district_id' => District::where('province_id', 6)->where('name', 'Çankaya')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'cargo', 'payment_method' => 'transfer', 'consent' => '1',
        ], $override);
    }

    private function carrier(string $name): StoreCarrier
    {
        return StoreCarrier::where('name', $name)->firstOrFail();
    }

    private function priceCarriers(): void
    {
        $this->carrier('Aras Kargo')->update(['fee' => '89.90']);
        $this->carrier('Sürat Kargo')->update(['fee' => '74.90']);
    }

    private function actAsAdmin(): void
    {
        $this->flushSession();
        $this->actingAs(StoreAdmin::create(['name' => 'Sipariş Sorumlusu', 'email' => 's@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    public function test_without_carrier_prices_the_fallback_cargo_fee_applies_and_no_carrier_is_chosen(): void
    {
        $this->post(self::HOST.'/odeme', $this->form())->assertRedirect()->assertSessionHasNoErrors();

        $order = StoreOrder::sole();
        $this->assertSame(['30.00', '230.00', null, null], [(string) $order->shipping_fee, (string) $order->total, $order->carrier_id, $order->carrier]);
    }

    public function test_customer_chooses_a_priced_carrier_and_its_fee_is_charged(): void
    {
        $this->priceCarriers();
        $surat = $this->carrier('Sürat Kargo');

        $this->get(self::HOST.'/odeme')->assertOk()->assertSee('Kargo firması')->assertSee('Sürat Kargo')->assertSee('Aras Kargo')
            ->assertSee('images/carriers/surat.png')->assertDontSee('Yurtiçi Kargo');

        // Firma seçilmeden, ücretsiz (fiyatı girilmemiş) firmayla ya da kapalı firmayla sipariş verilemez.
        $this->post(self::HOST.'/odeme', $this->form())->assertSessionHasErrors('carrier_id');
        $this->post(self::HOST.'/odeme', $this->form(['carrier_id' => $this->carrier('Yurtiçi Kargo')->id]))->assertSessionHasErrors('carrier_id');
        $this->carrier('Aras Kargo')->update(['is_active' => false]);
        $this->post(self::HOST.'/odeme', $this->form(['carrier_id' => $this->carrier('Aras Kargo')->id]))->assertSessionHasErrors('carrier_id');
        $this->assertSame(0, StoreOrder::count());

        // Tarayıcıdan gelen ücret yok sayılır; ücret firmanın kayıtlı ücretidir.
        $this->post(self::HOST.'/odeme', $this->form(['carrier_id' => $surat->id, 'shipping_fee' => '0']))->assertRedirect()->assertSessionHasNoErrors();
        $order = StoreOrder::sole();
        $this->assertSame(['74.90', '274.90', $surat->id, 'Sürat Kargo'], [(string) $order->shipping_fee, (string) $order->total, $order->carrier_id, $order->carrier]);
        $this->assertStringContainsString('Kargo (Sürat Kargo)', $order->consents()->firstOrFail()->body);
    }

    public function test_above_the_free_shipping_threshold_the_customer_does_not_choose_a_carrier(): void
    {
        $this->priceCarriers();
        StoreSetting::put(['shipping_free_threshold' => '150.00']);

        $this->get(self::HOST.'/odeme')->assertOk()->assertSee('Kargo ücretsiz')->assertDontSee('name="carrier_id"', false);
        $this->post(self::HOST.'/odeme', $this->form(['carrier_id' => $this->carrier('Aras Kargo')->id]))->assertRedirect()->assertSessionHasNoErrors();

        $order = StoreOrder::sole();
        $this->assertSame(['0.00', '200.00', null], [(string) $order->shipping_fee, (string) $order->total, $order->carrier_id]);
    }

    public function test_shipping_sends_tracking_email_and_customer_sees_where_the_parcel_is(): void
    {
        $this->priceCarriers();
        $customer = StoreCustomer::create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@ornek.com', 'password' => self::PASSWORD]);
        $this->post(self::HOST.'/odeme', $this->form(['carrier_id' => $this->carrier('Aras Kargo')->id]))->assertRedirect();
        $order = StoreOrder::sole();
        $order->forceFill(['customer_id' => $customer->id, 'status' => 'preparing', 'payment_status' => 'paid'])->save();

        $this->actAsAdmin();
        $this->get(route('store_admin.orders.show', $order))->assertOk()->assertSee('Müşterinin seçtiği firma.');
        $this->post(route('store_admin.orders.ship', $order), ['carrier_id' => $order->carrier_id, 'tracking_number' => 'ARS 123'])->assertSessionHasNoErrors();

        // Takip bağlantısı girilmemiş olsa da Aras'ınki kendiliğinden kullanılır ve numarayı içerir.
        $order->refresh();
        $this->assertSame('https://kargotakip.araskargo.com.tr/mainpage.aspx?code=ARS%20123', $order->tracking_url);
        $this->assertTrue($order->trackingIsDirect());

        $this->app->terminate();
        $mail = collect($this->mails)->first(fn ($m) => str_starts_with($m['subject'], 'Kargonuz yola çıktı'));
        $this->assertNotNull($mail);
        $this->assertSame([$order->tracking_url, 'Kargonuz nerede?'], [$mail['content']['action_url'], $mail['content']['action_label']]);
        $this->assertContains(['Kargo firması', 'Aras Kargo'], $mail['content']['rows']);
        $this->assertContains(['Takip numarası', 'ARS 123'], $mail['content']['rows']);

        $this->flushSession();
        $this->actingAs($customer, 'customer');
        $this->get(route('store.orders'))->assertOk()->assertSee('Kargom nerede')->assertSee($order->tracking_url, false);
        $this->get(route('store.orders.show', $order->order_no))->assertOk()->assertSee('Kargom nerede')->assertSee('images/carriers/aras.png')
            ->assertDontSee('kutuya yapıştırın');
    }

    public function test_carriers_whose_page_needs_the_number_typed_get_a_hint(): void
    {
        $this->priceCarriers();
        $this->post(self::HOST.'/odeme', $this->form(['carrier_id' => $this->carrier('Sürat Kargo')->id]))->assertRedirect();
        $order = StoreOrder::sole();
        $order->forceFill(['status' => 'preparing', 'payment_status' => 'paid'])->save();

        $this->actAsAdmin();
        $this->post(route('store_admin.orders.ship', $order), ['carrier_id' => $order->carrier_id, 'tracking_number' => 'SRT9'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('https://suratkargo.com.tr/KargoTakip/', $order->tracking_url);
        $this->assertFalse($order->trackingIsDirect());

        $this->app->terminate();
        $mail = collect($this->mails)->first(fn ($m) => str_starts_with($m['subject'], 'Kargonuz yola çıktı'));
        $this->assertStringContainsString('takip numaranızı oradaki kutuya yazın', $mail['content']['intro']);
        $this->get(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]))->assertOk()->assertSee('kutuya yapıştırın');
    }

    public function test_admin_sets_carrier_fee_and_known_carriers_are_recognised(): void
    {
        $this->actAsAdmin();
        $this->post(route('store_admin.carriers.store'), ['name' => 'DHL Express', 'fee' => 'abc'])->assertSessionHasErrors('fee');
        $this->post(route('store_admin.carriers.store'), ['name' => 'DHL Express', 'fee' => '149,90'])->assertSessionHasNoErrors();
        $dhl = StoreCarrier::where('name', 'DHL Express')->sole();
        $this->assertSame('149.90', (string) $dhl->fee);
        $this->assertSame('https://www.dhl.com/tr-tr/home/tracking.html?submit=1&tracking-id=9%2F1', $dhl->trackingUrl('9/1'));

        $this->get(route('store_admin.carriers.index'))->assertOk()->assertSee('images/carriers/dhl.png')->assertSee('Müşteriye ₺149,90 olarak sunulur');

        foreach (['yurtiçi kargo' => 'yurtici', 'YURTİÇİ' => 'yurtici', 'MNG Kargo' => 'mng', 'DHL eCommerce' => 'mng', 'Ptt Kargo' => 'ptt',
            'Sürat' => 'surat', 'UPS Kargo' => 'ups', 'Kolay Gelsin' => 'kolaygelsin'] as $name => $key) {
            $this->assertSame($key, CarrierBrands::match($name)['key'] ?? null, $name);
        }
        $this->assertNull(CarrierBrands::match('Bizim Kurye'));
        foreach (array_keys(CarrierBrands::BRANDS) as $key) {
            $this->assertFileExists(public_path("images/carriers/{$key}.png"));
        }
    }
}
