<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\CheckoutService;
use App\Models\AuditLog;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCodBlock;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreReturn;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Mağaza yönetiminde siparişler (M6): durum geçişleri, stok etkisi, engel listesi; ödeme ayarları ve yasal metinler. */
class StoreOrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private StoreProductVariant $pen;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'cod_enabled' => '1', 'cod_max' => '5000.00']);
        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $this->pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '50.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($this->pen, 10);
        $this->actingAs(StoreAdmin::create(['name' => 'Sipariş Sorumlusu', 'email' => 's@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    private function codOrder(string $phone = '05321234567', string $delivery = 'local', int $qty = 3): StoreOrder
    {
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $this->pen->id, $qty);
        $checkout = app(CheckoutService::class);
        $draft = $checkout->validate([
            'email' => 'musteri@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => $phone, 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => $delivery, 'payment_method' => 'cod', 'consent' => '1',
        ], null, $carts->summary($cart));

        return $checkout->place($draft, null, $cart, '127.0.0.1', 'test');
    }

    public function test_cod_order_lifecycle_from_approval_to_delivery(): void
    {
        $order = $this->codOrder();
        $this->assertSame('awaiting_approval', $order->status);
        $this->get(route('store_admin.orders.index', ['status' => 'new']))->assertOk()->assertSee($order->order_no);
        $this->get(route('store_admin.orders.show', $order))->assertOk()->assertSee('Teyit edildi, onayla');

        $this->post(route('store_admin.orders.ship', $order))->assertSessionHasErrors('order'); // önce onay
        $this->post(route('store_admin.orders.approve', $order))->assertSessionHasNoErrors();
        $this->post(route('store_admin.orders.approve', $order))->assertSessionHasErrors('order'); // iki kez uygulanmaz
        $this->post(route('store_admin.orders.ship', $order))->assertSessionHasNoErrors();
        $this->post(route('store_admin.orders.deliver', $order))->assertSessionHasErrors('order'); // tahsilat türü zorunlu
        $this->post(route('store_admin.orders.deliver', $order), ['collected_method' => 'cash'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(['delivered', 'collected', 'cash'], [$order->status, $order->payment_status, $order->collected_method]);
        $this->assertSame(['awaiting_approval', 'preparing', 'shipped', 'delivered'], $order->events()->pluck('status')->all());
        $this->assertSame(['store_admin'], $order->events()->reorder()->whereNot('status', 'awaiting_approval')->distinct()->pluck('actor_type')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.order_delivered', 'entity_id' => $order->id]);

        // Teslim edilmiş kapıda ödemeli siparişi olan numaranın sonraki siparişi onay beklemez.
        $this->assertSame('preparing', $this->codOrder()->status);
    }

    public function test_not_delivered_restocks_and_blocks_phone(): void
    {
        $order = $this->codOrder();
        $this->post(route('store_admin.orders.approve', $order));
        $this->post(route('store_admin.orders.ship', $order));
        $this->assertSame(7, $this->pen->fresh()->stock_quantity);

        $this->post(route('store_admin.orders.not-delivered', $order), ['reason' => 'Kapıda teslim almadı'])->assertSessionHasNoErrors();
        $this->assertSame(['not_delivered', 'void'], [$order->fresh()->status, $order->fresh()->payment_status]);
        $this->assertSame(10, $this->pen->fresh()->stock_quantity);
        $this->assertTrue(StoreCodBlock::where('phone', '05321234567')->exists());

        $this->get(route('store_admin.orders.show', $order))->assertOk()->assertSee('engel listesinde');
        $this->delete(route('store_admin.cod-blocks.destroy', StoreCodBlock::firstOrFail()))->assertSessionHasNoErrors();
        $this->assertSame(0, StoreCodBlock::count());
        $this->get(route('store_admin.activity'))->assertOk()
            ->assertSee('Sipariş teslim alınmadı')->assertSee('Sipariş '.$order->order_no)->assertSee('Telefon kapıda ödeme engelinden çıkarıldı');
    }

    public function test_not_delivered_blocks_the_address_for_other_phones_too(): void
    {
        $order = $this->codOrder();
        $this->post(route('store_admin.orders.approve', $order));
        $this->post(route('store_admin.orders.ship', $order));
        $this->post(route('store_admin.orders.not-delivered', $order), ['reason' => 'Kapıda teslim almadı'])->assertSessionHasNoErrors();
        $this->assertNotNull(StoreCodBlock::sole()->address_key);

        $tepebasi = District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id');
        $attempt = function (string $phone, string $address) use ($tepebasi) {
            $carts = app(CartService::class);
            $cart = $carts->createGuest();
            $carts->add($cart, $this->pen->id, 1);

            return app(CheckoutService::class)->validate([
                'email' => 'baska@ornek.com',
                'address' => ['first_name' => 'Ali', 'last_name' => 'Kaya', 'phone' => $phone, 'province_id' => 26, 'district_id' => $tepebasi, 'address_line' => $address],
                'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
            ], null, $carts->summary($cart));
        };

        // Aynı adres farklı yazılsa da (kısaltma, noktalama, büyük harf) başka numarayla kapıda ödeme yapılamaz.
        try {
            $attempt('05330001122', 'ATATÜRK BLV. No: 5 Daire: 2');
            $this->fail('adres engeli');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('adresine kapıda ödeme kullanılamıyor', $e->errors()['payment_method'][0]);
        }
        $this->assertSame('cod', $attempt('05330001122', 'İnönü Caddesi No 12 D 4')['payment_method'], 'başka adres serbest');

        $this->get(route('store_admin.orders.show', $order))->assertSee('engel listesinde');
        $this->get(route('store_admin.payments.edit'))->assertSee('Adres: ');
    }

    public function test_cancel_restocks_and_card_paid_orders_are_protected(): void
    {
        $order = $this->codOrder();
        $this->post(route('store_admin.orders.cancel', $order), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('store_admin.orders.cancel', $order), ['reason' => 'Müşteri vazgeçti'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $this->pen->fresh()->stock_quantity);
        $this->post(route('store_admin.orders.cancel', $order), ['reason' => 'Tekrar'])->assertSessionHasErrors('order');
        $this->assertSame(10, $this->pen->fresh()->stock_quantity, 'iade bir kez yapılır');

        $paid = $this->codOrder('05329998877');
        $paid->forceFill(['payment_method' => 'card', 'payment_status' => 'paid', 'status' => 'preparing'])->save();
        $this->post(route('store_admin.orders.cancel', $paid), ['reason' => 'Deneme'])->assertSessionHasErrors('order');
    }

    public function test_payment_settings_store_secrets_encrypted_and_validate(): void
    {
        $this->get(route('store_admin.payments.edit'))->assertOk()->assertSee('Odunpazarı');

        $form = ['shipping_cargo_fee' => '49,90', 'shipping_free_threshold' => '750', 'local_delivery_enabled' => '1', 'local_delivery_fee' => '0',
            'local_districts' => [District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id')],
            'cod_enabled' => '1', 'cod_max' => '2.500,00', 'cod_fee' => '15', 'cod_max_open' => 2, 'cod_first_order_approval' => '1', 'return_days' => 14,
            'card_enabled' => '1', 'iyzico_mode' => 'sandbox', 'iyzico_api_key' => 'api-123', 'iyzico_secret_key' => 'gizli-456', 'sms_driver' => 'log'];

        $this->put(route('store_admin.payments.update'), ['cod_max' => 'abc'] + $form)->assertSessionHasErrors('cod_max');
        $this->put(route('store_admin.payments.update'), ['local_districts' => []] + $form)->assertSessionHasErrors('local_districts');
        $this->put(route('store_admin.payments.update'), $form)->assertSessionHasNoErrors();

        $this->assertSame(['49.90', '750.00', '2500.00', '15.00', '2'], [StoreSetting::get('shipping_cargo_fee'), StoreSetting::get('shipping_free_threshold'),
            StoreSetting::get('cod_max'), StoreSetting::get('cod_fee'), StoreSetting::get('cod_max_open')]);
        $this->assertNotSame('gizli-456', StoreSetting::get('iyzico_secret_key'), 'şifreli saklanır');
        $this->assertSame('gizli-456', StoreSetting::secret('iyzico_secret_key'));
        $this->assertTrue(app(CheckoutService::class)->cardAvailable());

        // Boş bırakılan gizli alan mevcut değeri korur; değer ekrana ve işlem geçmişine yazılmaz.
        $this->put(route('store_admin.payments.update'), array_merge($form, ['iyzico_secret_key' => '']));
        $this->assertSame('gizli-456', StoreSetting::secret('iyzico_secret_key'));
        $this->get(route('store_admin.payments.edit'))->assertDontSee('gizli-456')->assertDontSee('api-123');
        foreach (AuditLog::where('action', 'store.payment_settings_updated')->get() as $log) {
            $this->assertStringNotContainsString('gizli-456', json_encode([$log->old_values, $log->new_values]));
            $this->assertStringNotContainsString('api-123', json_encode([$log->old_values, $log->new_values]));
        }

        // İade anlaşma kodu firmasız girilemez.
        $this->put(route('store_admin.payments.update'), array_merge($form, ['return_code' => 'IADE-1']))->assertSessionHasErrors('return_carrier');
        $this->put(route('store_admin.payments.update'), array_merge($form, ['return_carrier' => 'Aras Kargo', 'return_code' => 'IADE-1']))->assertSessionHasNoErrors();
        $this->assertSame(['carrier' => 'Aras Kargo', 'code' => 'IADE-1'], StoreReturn::returnCode());

        // iyzico Korumalı Alışveriş rozeti: token biçimi doğrulanır; girilince vitrinde betik yüklenir ve CSP izin verir.
        $this->put(route('store_admin.payments.update'), array_merge($form, ['iyzico_badge_token' => '<script>']))->assertSessionHasErrors('iyzico_badge_token');
        $this->get('http://magaza.localhost/')->assertDontSee('iyzico-buyer-protection');
        $this->put(route('store_admin.payments.update'), array_merge($form, ['iyzico_badge_token' => '2E548140-482d-402d-9ae8-191cf19f807d', 'iyzico_badge_position' => 'bottomRight']))
            ->assertSessionHasNoErrors();
        $home = $this->get('http://magaza.localhost/')->assertOk()
            ->assertSee('<meta name="iyzico-buyer-protection" content="2e548140-482d-402d-9ae8-191cf19f807d" data-position="bottomRight">', false);
        $this->assertStringContainsString('https://*.iyzipay.com', $home->headers->get('Content-Security-Policy'));
    }

    public function test_legal_documents_are_versioned(): void
    {
        $this->get(route('store_admin.legal.index'))->assertOk()->assertSee('Mesafeli Satış Sözleşmesi');
        $this->get(route('store_admin.legal.edit', 'on-bilgilendirme'))->assertOk()->assertSee('{ALICI_AD}');
        $this->put(route('store_admin.legal.update', 'on-bilgilendirme'), ['title' => 'Ön Bilgilendirme Formu', 'body' => 'kısa'])->assertSessionHasErrors('body');
        $this->put(route('store_admin.legal.update', 'on-bilgilendirme'), ['title' => 'Ön Bilgilendirme Formu', 'body' => str_repeat('Yeni metin {ALICI_AD}. ', 20)])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, StoreLegalDocument::current('on-bilgilendirme')->version);
        $this->assertSame(1, StoreLegalDocument::where('key', 'on-bilgilendirme')->where('version', 2)->count(), 'eski sürüm korunur');
        $this->get(route('store_admin.legal.index'))->assertSee('Güncel sürüm 3');
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.legal_published']);
    }

    public function test_admin_order_pages_are_not_reachable_from_customer_or_b2b(): void
    {
        $order = $this->codOrder();
        auth('store_admin')->logout();
        $this->flushSession();
        $this->actingAs($this->makeBusiness()->user, 'business');

        $this->get(route('store_admin.orders.show', $order))->assertRedirect(route('store_admin.login'));
        $this->post(route('store_admin.orders.approve', $order))->assertRedirect(route('store_admin.login'));
        $this->assertSame('awaiting_approval', $order->fresh()->status);
    }
}
