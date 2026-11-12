<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Orders\DeliveryRules;
use App\Domain\Store\Payments\PaymentSettings;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Teslimat ve ödeme seçenekleri (2026-11-12):
 *  - Eskişehir dışı: yalnızca kargo; kart veya havale; teslimde ödeme hiç yok.
 *  - Eskişehir: mağazadan teslim al (ücretsiz; kart veya "Mağazada öde"), seçili ilçelerde eve teslim
 *    (en düşük tutar, ücret, ücretsiz sınır; kart veya "Kapıda öde"). Eve teslim yapılabilen adreste kargo gösterilmez.
 */
class StoreDeliveryOptionsTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    /** @var list<array{to: string, subject: string, content: array}> */
    public array $mails = [];

    private StoreProductVariant $item;

    private StoreCart $cart;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put([
            'is_open' => '1', 'address' => 'Arifiye Mah. Hamamyolu Cad. No 1, Odunpazarı / Eskişehir', 'phone' => '0222 111 22 33',
            'shipping_cargo_fee' => '60.00', 'shipping_free_threshold' => '1500.00',
            'local_delivery_enabled' => '1', 'local_delivery_fee' => '75.00', 'local_min_total' => '250.00', 'local_free_threshold' => '500.00',
            'cod_enabled' => '1', 'cod_max' => '5000.00', 'cod_fee' => '0.00', 'cod_first_order_approval' => '0', 'cod_max_open' => '10',
            'transfer_enabled' => '1', 'transfer_accounts' => 'Ziraat Bankası | Yapboz Kırtasiye | TR330006100519786457841326',
        ]);
        StoreSetting::put(['local_districts' => $this->district('Odunpazarı').','.$this->district('Tepebaşı')]);

        $this->item = StoreProduct::create(['name' => 'Defter', 'slug' => 'defter', 'is_published' => true])
            ->variants()->create(['sku' => 'DEF-1', 'price' => '100.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($this->item, 50);
        $this->cart = app(CartService::class)->createGuest();
        $this->withCookie(CurrentCart::COOKIE, $this->cart->token);

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
    }

    private function district(string $name, int $province = 26): int
    {
        return District::where('province_id', $province)->where('name', $name)->value('id');
    }

    private function fill(int $quantity): void
    {
        $this->cart->items()->delete();
        app(CartService::class)->add($this->cart, $this->item->id, $quantity);
    }

    private function form(string $delivery, string $payment, string $district = 'Odunpazarı', int $province = 26, array $extra = []): array
    {
        return array_replace_recursive([
            'email' => 'musteri@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '0532 123 45 67', 'province_id' => $province,
                'district_id' => $this->district($district, $province), 'neighborhood' => 'Arifiye', 'address_line' => 'Hamamyolu Cad. No 12 D 3'],
            'billing_type' => 'individual', 'billing_tckn' => '10000000146', 'billing_same' => '1',
            'delivery_method' => $delivery, 'payment_method' => $payment, 'consent' => '1',
        ], $extra);
    }

    public function test_delivery_options_follow_province_district_and_amount(): void
    {
        $rules = app(DeliveryRules::class);
        $odunpazari = $this->district('Odunpazarı');

        // Eskişehir dışı: yalnızca kargo.
        $this->assertSame(['cargo' => '60.00'], $rules->options(6, $this->district('Çankaya', 6), '300.00'));
        // Eskişehir'de eve teslim olmayan ilçe: kargo + mağazadan teslim al.
        $this->assertSame(['cargo' => '60.00', 'pickup' => '0.00'], $rules->options(26, $this->district('Sivrihisar'), '300.00'));
        // Eve teslim ilçesi, en düşük tutarın altı: eve teslim yok, kargo görünür.
        $this->assertSame(['cargo' => '60.00', 'pickup' => '0.00'], $rules->options(26, $odunpazari, '249.99'));
        // 250 ₺ ve üzeri: eve teslim 75 ₺, kargo gösterilmez.
        $this->assertSame(['pickup' => '0.00', 'local' => '75.00'], $rules->options(26, $odunpazari, '250.00'));
        // 500 ₺ ve üzeri: eve teslim ücretsiz.
        $this->assertSame(['pickup' => '0.00', 'local' => '0.00'], $rules->options(26, $odunpazari, '500.00'));
        // Kargo gizleme kapatılırsa kargo da seçilebilir; ücretsiz kargo sınırı kargoya uygulanır.
        StoreSetting::put(['local_hide_cargo' => '0']);
        $this->assertSame(['cargo' => '0.00', 'pickup' => '0.00', 'local' => '0.00'], $rules->options(26, $odunpazari, '1500.00'));
        // Mağazadan teslim al kapatılırsa sunulmaz.
        StoreSetting::put(['pickup_enabled' => '0']);
        $this->assertArrayNotHasKey('pickup', $rules->options(26, $odunpazari, '300.00'));
    }

    public function test_pickup_with_pay_at_store_is_free_and_ready_mail_has_store_address(): void
    {
        $this->fill(1); // 100 ₺: eve teslim alt sınırının altında, mağazadan teslim al yine olur
        $this->post(self::HOST.'/odeme', $this->form('pickup', 'cod', 'Sivrihisar', 26, ['total' => '1']))->assertRedirect();
        $order = StoreOrder::sole();
        $this->assertSame(['pickup', 'cod', 'preparing', '100.00', '0.00', '100.00'],
            [$order->delivery_method, $order->payment_method, $order->status, $order->subtotal, $order->shipping_fee, $order->total]);
        $this->assertSame(['Mağazada ödeme', 'Mağazadan teslim al'], [$order->paymentLabel(), $order->deliveryLabel()]);
        $this->app->terminate();
        $this->assertStringContainsString('mağazada nakit veya kartla (POS)', collect($this->mails)->firstWhere('to', 'musteri@ornek.com')['content']['intro']);

        $page = $this->get(self::HOST."/siparis/{$order->order_no}/{$order->access_token}")->assertOk();
        $page->assertSee('Mağazada ödeme')->assertSee('Mağazadan teslim alacaksınız')->assertSee('Hamamyolu Cad. No 1')->assertDontSee('Teslimatta ödeme');

        // Yönetici: "Teslime hazır" → müşteriye mağaza adresli e-posta; teslimde tahsilat türü (nakit / POS) zorunlu.
        $this->mails = [];
        $this->actingAs(StoreAdmin::create(['name' => 'Yönetici', 'email' => 'y@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
        $this->get(route('store_admin.orders.show', $order))->assertOk()->assertSee('Teslime hazır')->assertSee('Mağazadan teslim alınacak');
        $this->post(route('store_admin.orders.ship', $order))->assertSessionHasNoErrors();
        $this->app->terminate();
        $order->refresh();
        $this->assertSame(['shipped', 'Teslime hazır'], [$order->status, $order->statusLabel()]);
        $mail = collect($this->mails)->firstWhere('subject', "Siparişiniz mağazada hazır — {$order->order_no}");
        $this->assertSame('musteri@ornek.com', $mail['to'] ?? null);
        $this->assertContains(['Mağaza adresi', 'Arifiye Mah. Hamamyolu Cad. No 1, Odunpazarı / Eskişehir'], $mail['content']['rows']);

        $this->get(route('store_admin.orders.show', $order))->assertSee('Mağazada tahsilat')->assertSee('POS (kart)');
        $this->post(route('store_admin.orders.deliver', $order))->assertSessionHasErrors('order');
        $this->post(route('store_admin.orders.deliver', $order), ['collected_method' => 'card'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(['delivered', 'collected', 'card'], [$order->status, $order->payment_status, $order->collected_method]);
        $this->assertStringContainsString('mağazada tahsil edildi (POS ile kart)', $order->events()->where('status', 'delivered')->value('note'));
    }

    public function test_home_delivery_minimum_fee_and_free_threshold_are_enforced_on_the_server(): void
    {
        // 200 ₺: eve teslim seçilemez.
        $this->fill(2);
        $this->post(self::HOST.'/odeme', $this->form('local', 'cod'))->assertSessionHasErrors('delivery_method');

        // 300 ₺: 75 ₺ eve teslim ücreti, kapıda ödeme.
        $this->fill(3);
        $this->post(self::HOST.'/odeme', $this->form('local', 'cod', 'Odunpazarı', 26, ['shipping_fee' => '0']))->assertRedirect();
        $order = StoreOrder::sole();
        $this->assertSame(['local', '75.00', '375.00', 'Kapıda ödeme', 'Eve teslim (Eskişehir)'],
            [$order->delivery_method, $order->shipping_fee, $order->total, $order->paymentLabel(), $order->deliveryLabel()]);

        // 500 ₺ ve üzeri: ücretsiz.
        $this->fill(5);
        $this->post(self::HOST.'/odeme', $this->form('local', 'cod', 'Tepebaşı'))->assertRedirect();
        $this->assertSame(['0.00', '500.00'], [StoreOrder::latest('id')->first()->shipping_fee, StoreOrder::latest('id')->first()->total]);

        // Eve teslim yapılabilen adreste kargo gösterilmez (sunucu da kabul etmez).
        $this->fill(3);
        $this->post(self::HOST.'/odeme', $this->form('cargo', 'transfer'))->assertSessionHasErrors('delivery_method');
    }

    public function test_pay_on_delivery_and_transfer_are_limited_to_their_methods(): void
    {
        $this->fill(3);
        // Eskişehir dışında kapıda / mağazada ödeme yok.
        $this->post(self::HOST.'/odeme', $this->form('cargo', 'cod', 'Çankaya', 6))
            ->assertSessionHasErrors(['payment_method' => "Kapıda ve mağazada ödeme yalnızca Eskişehir'de geçerlidir."]);
        $this->post(self::HOST.'/odeme', $this->form('pickup', 'cod', 'Çankaya', 6))->assertSessionHasErrors('delivery_method');
        // Havale / EFT yalnızca kargoda.
        $this->post(self::HOST.'/odeme', $this->form('pickup', 'transfer'))->assertSessionHasErrors('payment_method');
        $this->post(self::HOST.'/odeme', $this->form('local', 'transfer'))->assertSessionHasErrors('payment_method');
        $this->assertSame(0, StoreOrder::count());

        // Başka şehre kargo + havale: ücretsiz kargo sınırının altında kargo ücreti alınır.
        $this->post(self::HOST.'/odeme', $this->form('cargo', 'transfer', 'Çankaya', 6))->assertRedirect();
        $order = StoreOrder::sole();
        $this->assertSame(['cargo', 'transfer', 'awaiting_transfer', '60.00', '360.00'],
            [$order->delivery_method, $order->payment_method, $order->status, $order->shipping_fee, $order->total]);
    }

    public function test_card_payment_works_with_pickup(): void
    {
        StoreSetting::put(['card_enabled' => '1', 'iyzico_mode' => 'sandbox']);
        StoreSetting::putSecret('iyzico_api_key', 'sandbox-api-key');
        StoreSetting::putSecret('iyzico_secret_key', 'sandbox-secret-key');
        Http::fake(['*/checkoutform/initialize/*' => Http::response(['status' => 'success', 'token' => 'tok-1', 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme'])]);

        $this->fill(1);
        $this->post(self::HOST.'/odeme', $this->form('pickup', 'card', 'Sivrihisar'))->assertRedirect('https://sandbox.iyzico.test/odeme');
        $order = StoreOrder::sole();
        $this->assertSame(['pickup', 'card', 'pending_payment', '0.00', '100.00'],
            [$order->delivery_method, $order->payment_method, $order->status, $order->shipping_fee, $order->total]);
    }

    public function test_checkout_page_names_methods_for_customers_and_settings_are_dynamic(): void
    {
        $this->fill(3);
        $this->get(self::HOST.'/odeme')->assertOk()->assertSee('Mağazadan teslim al')->assertSee('Eve teslim')
            ->assertSee('Hamamyolu Cad. No 1')->assertDontSee('Teslimatta ödeme')->assertDontSee('doğrulama kodu');

        // Ödeme ve teslimat ayarları: eve teslim kuralları panelden değişir; SMS alanları yoktur.
        $this->actingAs(StoreAdmin::create(['name' => 'Yönetici', 'email' => 'y@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
        $this->get(route('store_admin.payments.edit'))->assertOk()->assertSee('Teslimatta ödeme')
            ->assertSee('Yalnızca Eskişehir&#039;de, mağazadan teslim al ve eve teslimde görünür.', false)->assertDontSee('Netgsm');
        app(PaymentSettings::class)->save([
            'cod_max_open' => 1, 'return_days' => 14, 'iyzico_mode' => 'sandbox', 'transfer_days' => 3,
            'pickup_enabled' => '1', 'local_delivery_enabled' => '1', 'local_hide_cargo' => '1',
            'local_min_total' => '300', 'local_delivery_fee' => '50', 'local_free_threshold' => '',
            'local_districts' => [$this->district('Odunpazarı')],
        ]);
        $this->assertSame(['300.00', '50.00', null], [StoreSetting::get('local_min_total'), StoreSetting::get('local_delivery_fee'), StoreSetting::get('local_free_threshold')]);
        $rules = app(DeliveryRules::class);
        // Formda yedek kargo ücreti boş bırakıldı: 0 olarak kaydedilir.
        $this->assertSame(['cargo' => '0.00', 'pickup' => '0.00'], $rules->options(26, $this->district('Odunpazarı'), '299.99'));
        $this->assertSame(['pickup' => '0.00', 'local' => '50.00'], $rules->options(26, $this->district('Odunpazarı'), '900.00'));
    }
}
