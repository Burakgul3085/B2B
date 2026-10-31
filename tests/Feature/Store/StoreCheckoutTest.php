<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\CheckoutService;
use App\Domain\Store\Orders\LegalDocuments;
use App\Domain\Store\Sms\SmsGateway;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\District;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCodBlock;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreOtpCode;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use App\Models\Store\StoreStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Ödeme akışı (M6): sözleşme onayı, tutarın sunucuda hesaplanması, kapıda ödeme + SMS, iyzico kart ödemesi. */
class StoreCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    /** @var list<array{phone: string, body: string}> */
    private array $sms = [];

    private StoreProductVariant $pen;

    private StoreCart $cart;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'company_title' => 'Yapboz Kırtasiye Ltd. Şti.']);

        // SMS'i yakalayan sahte gönderici (kod veritabanında yalnızca özet olarak tutulduğu için).
        $test = $this;
        $this->instance(SmsGateway::class, new class($test) extends SmsGateway
        {
            public function __construct(private $test) {}

            public function send(string $phone, string $body, string $purpose, ?string $maskedBody = null): bool
            {
                $this->test->captureSms($phone, $body);

                return true;
            }
        });

        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $this->pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($this->pen, 10);

        $this->cart = app(CartService::class)->createGuest();
        app(CartService::class)->add($this->cart, $this->pen->id, 2);
        $this->withCookie(CurrentCart::COOKIE, $this->cart->token);
    }

    public function captureSms(string $phone, string $body): void
    {
        $this->sms[] = ['phone' => $phone, 'body' => $body];
    }

    private function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sms)['body'], $m);

        return $m[1];
    }

    private function district(int $province, string $name): int
    {
        return District::where('province_id', $province)->where('name', $name)->value('id');
    }

    private function form(array $override = [], int $province = 26, string $district = 'Odunpazarı'): array
    {
        return array_replace_recursive([
            'email' => 'Ayse@Ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '0532 123 45 67', 'province_id' => $province,
                'district_id' => $this->district($province, $district), 'neighborhood' => 'Arifiye', 'address_line' => 'Hamamyolu Cad. No 12 D 3'],
            'billing_type' => 'individual',
            'billing_same' => '1',
            'delivery_method' => 'local',
            'payment_method' => 'cod',
            'consent' => '1',
        ], $override);
    }

    private function enableCod(array $extra = []): void
    {
        StoreSetting::put($extra + ['cod_enabled' => '1', 'cod_max' => '3000.00', 'cod_fee' => '10.00', 'local_delivery_fee' => '0.00']);
    }

    private function enableCard(): void
    {
        StoreSetting::put(['card_enabled' => '1', 'iyzico_mode' => 'sandbox']);
        StoreSetting::putSecret('iyzico_api_key', 'sandbox-api-key');
        StoreSetting::putSecret('iyzico_secret_key', 'sandbox-secret-key');
    }

    public function test_checkout_page_requires_a_valid_cart(): void
    {
        $this->get(self::HOST.'/odeme')->assertOk()->assertSee('Siparişi tamamla')->assertSee('Mesafeli Satış Sözleşmesi')
            ->assertSee('KVKK Aydınlatma Metni</a> kapsamında işlenir', false);
        $this->get(self::HOST.'/yasal/mesafeli-satis')->assertOk()->assertSee('Yapboz Kırtasiye Ltd. Şti.');

        $this->cart->items()->delete();
        $this->get(self::HOST.'/odeme')->assertRedirect(route('store.cart'));
    }

    public function test_validation_and_payment_method_rules(): void
    {
        $this->post(self::HOST.'/odeme', $this->form(['consent' => '0']))->assertSessionHasErrors('consent');
        $this->post(self::HOST.'/odeme', $this->form(['payment_method' => 'card']))->assertSessionHasErrors('payment_method'); // kart yapılandırılmamış
        $this->post(self::HOST.'/odeme', $this->form())->assertSessionHasErrors(['payment_method' => 'Kapıda ödeme şu anda kullanılamıyor.']);

        $this->enableCod();
        // Ankara adresine mağaza teslimatı ve kapıda ödeme yok.
        $this->post(self::HOST.'/odeme', $this->form([], 6, 'Çankaya'))->assertSessionHasErrors('delivery_method');
        $this->post(self::HOST.'/odeme', $this->form(['delivery_method' => 'cargo'], 6, 'Çankaya'))->assertSessionHasErrors('payment_method');
        // Seçili olmayan Eskişehir ilçesi
        StoreSetting::put(['local_districts' => (string) $this->district(26, 'Tepebaşı')]);
        $this->post(self::HOST.'/odeme', $this->form())->assertSessionHasErrors('delivery_method');
        StoreSetting::put(['local_districts' => null]);

        $this->post(self::HOST.'/odeme', $this->form(['billing_type' => 'corporate']))->assertSessionHasErrors(['billing_company', 'billing_tax_office', 'billing_tax_number']);
        $this->post(self::HOST.'/odeme', $this->form(['billing_tckn' => '12345678901']))->assertSessionHasErrors('billing_tckn');
        $this->assertTrue(CheckoutService::validTckn('10000000146'));
        $this->assertSame(0, StoreOrder::count());
    }

    public function test_cash_on_delivery_with_sms_verification(): void
    {
        $this->enableCod();

        // Tarayıcıdan gelen tutar/fiyat dikkate alınmaz.
        $this->post(self::HOST.'/odeme', $this->form(['total' => '1', 'price' => '1']))->assertRedirect(route('store.checkout.verify'));
        $this->assertSame('05321234567', $this->sms[0]['phone']);
        $this->assertSame(0, StoreOrder::count(), 'kod doğrulanmadan sipariş oluşmaz');
        $this->get(self::HOST.'/odeme/dogrulama')->assertOk()->assertSee('0532 123 45 67');

        $this->post(self::HOST.'/odeme/dogrulama', ['code' => '000000'])->assertSessionHasErrors(['code' => 'Kod hatalı. 4 deneme hakkınız kaldı.']);
        $this->assertSame(1, StoreOtpCode::value('attempts'), 'hatalı deneme sayılır');

        $response = $this->post(self::HOST.'/odeme/dogrulama', ['code' => $this->lastCode()]);
        $order = StoreOrder::firstOrFail();
        $response->assertRedirect(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]));

        $this->assertSame(['awaiting_approval', 'cod_pending', 'local', '200.00', '0.00', '10.00', '210.00'],
            [$order->status, $order->payment_status, $order->delivery_method, $order->subtotal, $order->shipping_fee, $order->cod_fee, $order->total]);
        $this->assertSame('35.00', $order->vat_total); // 33,33 ürün + 1,67 hizmet bedeli
        $this->assertSame(['ayse@ornek.com', 'Ayşe Yılmaz', 'Odunpazarı', 'Eskişehir'], [$order->email, $order->billing_name, $order->shipping_district, $order->shipping_province]);
        $this->assertMatchesRegularExpression('/^YB\d{6}$/', $order->order_no);

        // Stok satışa düştü, sepet boşaldı.
        $this->assertSame(8, $this->pen->fresh()->stock_quantity);
        $this->assertSame(1, StoreStockMovement::where('type', 'sale')->where('reference_id', $order->id)->count());
        $this->assertSame(0, $this->cart->items()->count());

        // Yasal metinler siparişe özel doldurulmuş hâliyle saklandı.
        $this->assertSame(2, $order->consents()->count());
        $consent = $order->consents()->where('document_key', 'mesafeli-satis')->firstOrFail();
        $this->assertStringContainsString('Ayşe Yılmaz', $consent->body);
        $this->assertStringContainsString('Jel Kalem × 2', $consent->body);
        $this->assertStringContainsString('Yapboz Kırtasiye Ltd. Şti.', $consent->body);

        $this->get(self::HOST."/siparis/{$order->order_no}/{$order->access_token}")->assertOk()->assertSee($order->order_no)->assertSee('Onay bekliyor');
        $this->get(self::HOST."/siparis/{$order->order_no}/".Str::random(40))->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.order_placed', 'entity_id' => $order->id]);
    }

    public function test_cod_limits_blocklist_and_open_orders(): void
    {
        $this->enableCod(['cod_max' => '150.00']);
        $this->post(self::HOST.'/odeme', $this->form())->assertSessionHasErrors('payment_method');

        $this->enableCod();
        StoreCodBlock::create(['phone' => '05321234567', 'reason' => 'Teslim almadı']);
        $this->post(self::HOST.'/odeme', $this->form())->assertSessionHasErrors(['payment_method' => 'Bu telefon numarasıyla kapıda ödeme kullanılamıyor. Kartla ödeyebilirsiniz.']);
        StoreCodBlock::query()->delete();

        // Açık kapıda ödemeli sipariş varken ikincisi açılamaz (varsayılan sınır 1).
        $this->post(self::HOST.'/odeme', $this->form());
        $this->post(self::HOST.'/odeme/dogrulama', ['code' => $this->lastCode()]);
        app(CartService::class)->add($this->cart, $this->pen->id, 1);
        $this->post(self::HOST.'/odeme', $this->form())->assertSessionHasErrors('payment_method');
    }

    public function test_sms_resend_cooldown_and_cart_change_during_verification(): void
    {
        $this->enableCod();
        $this->post(self::HOST.'/odeme', $this->form());
        $this->post(self::HOST.'/odeme/dogrulama/yeniden')->assertSessionHasErrors('code'); // 60 sn dolmadı
        $this->travel(61)->seconds();
        $this->post(self::HOST.'/odeme/dogrulama/yeniden')->assertSessionHas('status');
        $this->assertCount(2, $this->sms);

        // Kod beklenirken sepet değişirse sipariş oluşmaz.
        app(CartService::class)->add($this->cart, $this->pen->id, 1);
        $this->post(self::HOST.'/odeme/dogrulama', ['code' => $this->lastCode()])->assertRedirect(route('store.cart'));
        $this->assertSame(0, StoreOrder::count());
    }

    public function test_shipping_fees_and_free_threshold(): void
    {
        StoreSetting::put(['shipping_cargo_fee' => '49.90', 'shipping_free_threshold' => '500.00']);
        $this->enableCard();
        Http::fake(['*/checkoutform/initialize/*' => Http::response(['status' => 'success', 'token' => 'tok-1', 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme'])]);

        $this->post(self::HOST.'/odeme', $this->form(['delivery_method' => 'cargo', 'payment_method' => 'card'], 6, 'Çankaya'))->assertRedirect('https://sandbox.iyzico.test/odeme');
        $order = StoreOrder::firstOrFail();
        $this->assertSame(['200.00', '49.90', '249.90'], [$order->subtotal, $order->shipping_fee, $order->total]);
    }

    public function test_card_payment_success_via_iyzico(): void
    {
        $this->enableCard();
        Http::fake([
            '*/checkoutform/initialize/*' => Http::response(['status' => 'success', 'token' => 'tok-ok', 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme']),
            '*/checkoutform/auth/ecom/detail' => fn (HttpRequest $r) => Http::response([
                'status' => 'success', 'paymentStatus' => 'SUCCESS', 'paymentId' => '777', 'basketId' => StoreOrder::value('order_no'),
                'price' => 200.0, 'paidPrice' => 200.0, 'installment' => 1, 'cardFamily' => 'Bonus', 'lastFourDigits' => '0008', 'fraudStatus' => 1,
            ]),
        ]);

        $this->post(self::HOST.'/odeme', $this->form(['delivery_method' => 'cargo', 'payment_method' => 'card']))->assertRedirect('https://sandbox.iyzico.test/odeme');
        $order = StoreOrder::firstOrFail();
        $this->assertSame(['pending_payment', 'pending'], [$order->status, $order->payment_status]);
        $this->assertSame([$this->pen->id => 8], app(StoreStockService::class)->available([$this->pen->id]), 'stok ayrıldı');
        $this->assertSame(10, $this->pen->fresh()->stock_quantity, 'stok henüz düşmedi');
        $this->assertSame(1, $this->cart->items()->count(), 'ödeme bitene kadar sepet korunur');

        Http::assertSent(function (HttpRequest $r) use ($order) {
            if (! str_contains($r->url(), 'initialize')) {
                return false;
            }
            $auth = base64_decode(substr($r->header('Authorization')[0], 8));

            return str_starts_with($r->header('Authorization')[0], 'IYZWSv2 ') && str_starts_with($auth, 'apiKey:sandbox-api-key&randomKey:')
                && $r['basketId'] === $order->order_no && $r['paidPrice'] === '200.00' && $r['basketItems'][0]['price'] === '200.00'
                && $r['callbackUrl'] === route('store.payment.callback');
        });

        // iyzico geri dönüşü (başka siteden; CSRF yok, çerez yok)
        // Oturum açılmaz: müşterinin oturum çerezi ezilmez (müşteri çıkış yapmış olmaz).
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-ok'])
            ->assertRedirect(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token, 'odeme' => 'sonuc']))
            ->assertCookieMissing('magaza');
        $this->get(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token, 'odeme' => 'sonuc']))->assertSee('Ödemeniz alındı');

        $order->refresh();
        $this->assertSame(['paid', 'paid'], [$order->status, $order->payment_status]);
        $this->assertSame(8, $this->pen->fresh()->stock_quantity);
        $this->assertSame(0, $this->cart->items()->count());
        $payment = $order->payments()->firstOrFail();
        $this->assertSame(['succeeded', '777', '0008'], [$payment->status, $payment->provider_payment_id, $payment->card_last_four]);

        // Aynı geri dönüş ikinci kez gelirse hiçbir şey tekrar uygulanmaz.
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-ok']);
        $this->assertSame(1, StoreStockMovement::where('type', 'sale')->count());
    }

    public function test_card_payment_failure_or_amount_mismatch_releases_stock(): void
    {
        $this->enableCard();
        $results = [
            'tok-fail' => ['status' => 'success', 'paymentStatus' => 'FAILURE', 'errorMessage' => 'Kart limiti yetersiz'],
            'tok-mismatch' => ['status' => 'success', 'paymentStatus' => 'SUCCESS', 'paymentId' => '1', 'price' => 1.0, 'fraudStatus' => 1],
        ];
        $next = ['tok-fail', 'tok-mismatch'];
        Http::fake([
            '*/checkoutform/initialize/*' => function () use (&$next) {
                return Http::response(['status' => 'success', 'token' => array_shift($next), 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme']);
            },
            '*/checkoutform/auth/ecom/detail' => fn (HttpRequest $r) => Http::response($results[$r['token']] + ['basketId' => $r['conversationId']]),
        ]);

        foreach (array_keys($results) as $token) {
            $this->post(self::HOST.'/odeme', $this->form(['delivery_method' => 'cargo', 'payment_method' => 'card']));
            $order = StoreOrder::latest('id')->firstOrFail();
            $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => $token])->assertRedirect()->assertCookieMissing('magaza');
            $this->get(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token, 'odeme' => 'sonuc']))
                ->assertSee('Ödeme alınamadığı için sipariş tamamlanmadı')->assertDontSee('Ödemeniz alındı');
            $this->assertSame('payment_failed', $order->fresh()->status, $token);
            $this->assertSame([$this->pen->id => 10], app(StoreStockService::class)->available([$this->pen->id]), $token.': ayırma bırakıldı');
            $this->assertSame(1, $this->cart->items()->count(), 'sepet korunur, yeniden denenebilir');
        }
        $this->assertSame(0, StoreStockMovement::where('type', 'sale')->count());
    }

    public function test_stale_pending_payments_are_resolved(): void
    {
        $this->enableCard();
        Http::fake([
            '*/checkoutform/initialize/*' => Http::response(['status' => 'success', 'token' => 'tok-stale', 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme']),
            '*/checkoutform/auth/ecom/detail' => Http::response(['status' => 'success', 'paymentStatus' => 'INIT_THREEDS']),
        ]);
        $this->post(self::HOST.'/odeme', $this->form(['delivery_method' => 'cargo', 'payment_method' => 'card']));

        // Ödeme formu (30 dk) açıkken sipariş kapatılmaz; süresi geçince kapanır ve stok serbest kalır.
        $this->travel(31)->minutes();
        $this->artisan('store:expire-pending-payments')->assertSuccessful();
        $this->assertSame('pending_payment', StoreOrder::value('status'));
        $this->travel(5)->minutes();
        $this->artisan('store:expire-pending-payments')->assertSuccessful();
        $this->assertSame('payment_failed', StoreOrder::value('status'));
        $this->assertSame([$this->pen->id => 10], app(StoreStockService::class)->available([$this->pen->id]));
    }

    public function test_member_checkout_with_saved_address_and_new_legal_version(): void
    {
        $this->enableCod(['cod_first_order_approval' => '0']);
        app(LegalDocuments::class)->publish('mesafeli-satis', 'Mesafeli Satış Sözleşmesi', str_repeat('Yeni sürüm metni {ALICI_AD}. ', 20));
        $customer = StoreCustomer::create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'uye@ornek.com', 'password' => self::PASSWORD]);
        app(CartService::class)->add(app(CartService::class)->forCustomer($customer), $this->pen->id, 1);
        $this->actingAs($customer, 'customer');

        $this->post(self::HOST.'/odeme', $this->form(['save_address' => '1', 'address' => ['title' => 'Ev']]))->assertRedirect(route('store.checkout.verify'));
        $this->post(self::HOST.'/odeme/dogrulama', ['code' => $this->lastCode()]);

        $order = StoreOrder::firstOrFail();
        $this->assertSame([$customer->id, 'uye@ornek.com', 'preparing'], [$order->customer_id, $order->email, $order->status]);
        $this->assertSame(1, $customer->addresses()->count(), 'adres kaydedildi');
        $this->assertSame(4, $order->consents()->where('document_key', 'mesafeli-satis')->value('version'));
        $this->assertSame(4, StoreLegalDocument::where('key', 'mesafeli-satis')->max('version'));

        $this->get(self::HOST.'/hesabim/siparisler')->assertOk()->assertSee($order->order_no);
        $this->get(self::HOST."/hesabim/siparisler/{$order->order_no}")->assertOk();
        // Başka müşterinin siparişi görülemez.
        $other = StoreCustomer::create(['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@ornek.com', 'password' => self::PASSWORD]);
        $this->flushSession();
        $this->actingAs($other, 'customer')->get(self::HOST."/hesabim/siparisler/{$order->order_no}")->assertNotFound();
    }
}
