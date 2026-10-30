<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Support\Payments\IyzicoApi;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\AuditLog;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreOrder;
use App\Models\Store\StorePayment;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreRefund;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Ödeme güvenliği: çift gönderim, aynı sepetle yeniden ödeme, geç / ikinci ödemenin otomatik iadesi, ödeme bekleyen
 * sayfanın canlı kontrolü, bekleyen sipariş sınırı, iyzico imza kontrolü, sonucu belirsiz iade, test modu uyarıları.
 */
class StorePaymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreProductVariant $pen;

    private StoreCart $cart;

    private int $seq = 0;

    /** @var array<string, bool> iyzico'da ödenmiş belirteçler */
    private array $paid = [];

    /** success | fail | unreachable */
    private string $refundMode = 'success';

    /** @var list<array{0: string, 1: string}> iade istekleri: [işlem kimliği, tutar] */
    private array $refunds = [];

    private ?string $signature = null;

    /** Sahte iyzico'nun aynen döneceği ham yanıt (sayı biçimi testi) */
    private ?string $rawBody = null;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'card_enabled' => '1', 'iyzico_mode' => 'sandbox', 'local_delivery_fee' => '0.00']);
        StoreSetting::putSecret('iyzico_api_key', 'sandbox-api-key');
        StoreSetting::putSecret('iyzico_secret_key', 'sandbox-secret-key');

        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $this->pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($this->pen, 10);
        $this->cart = $this->newCart();

        Http::fake(['*' => fn (HttpRequest $r) => $this->iyzico($r)]);
    }

    private function newCart(int $qty = 2): StoreCart
    {
        $cart = app(CartService::class)->createGuest();
        app(CartService::class)->add($cart, $this->pen->id, $qty);
        $this->withCookie(CurrentCart::COOKIE, $cart->token);

        return $cart;
    }

    /** Sahte iyzico: sıralı belirteçler, yalnızca $paid'deki belirteçler ödenmiş görünür; iade davranışı ayarlanabilir. */
    private function iyzico(HttpRequest $r)
    {
        if ($this->rawBody !== null) {
            return Http::response($this->rawBody);
        }
        if (str_contains($r->url(), 'initialize')) {
            $this->seq++;

            return Http::response(['status' => 'success', 'token' => "tok-{$this->seq}", 'paymentPageUrl' => "https://sandbox.iyzico.test/odeme/{$this->seq}"]);
        }
        if (str_contains($r->url(), 'detail')) {
            $order = StoreOrder::where('order_no', $r['conversationId'])->first();
            if (! ($this->paid[$r['token']] ?? false)) {
                return Http::response(['status' => 'success', 'paymentStatus' => 'INIT_THREEDS', 'basketId' => $r['conversationId']]);
            }
            $items = $order->items()->get()->map(fn ($i) => ['itemId' => (string) $i->id, 'paymentTransactionId' => "tx-{$r['token']}-{$i->id}", 'paidPrice' => (float) $i->line_total])->all();
            $body = ['status' => 'success', 'paymentStatus' => 'SUCCESS', 'paymentId' => 'p-'.$r['token'], 'basketId' => $order->order_no, 'conversationId' => $order->order_no,
                'currency' => 'TRY', 'price' => (float) $order->total, 'paidPrice' => (float) $order->total, 'fraudStatus' => 1, 'token' => $r['token'], 'itemTransactions' => $items];
            if ($this->signature !== null) {
                $body['signature'] = $this->signature === 'valid'
                    ? hash_hmac('sha256', implode(':', ['SUCCESS', 'p-'.$r['token'], 'TRY', $order->order_no, $order->order_no, (float) $order->total, (float) $order->total, $r['token']]), 'sandbox-secret-key')
                    : str_repeat('a', 64);
            }

            return Http::response($body);
        }
        if (str_contains($r->url(), '/payment/refund')) {
            if ($this->refundMode === 'unreachable') {
                throw new ConnectionException('zaman aşımı');
            }
            $this->refunds[] = [$r['paymentTransactionId'], $r['price']];

            return $this->refundMode === 'success'
                ? Http::response(['status' => 'success'])
                : Http::response(['status' => 'failure', 'errorMessage' => 'Banka reddetti']);
        }

        return Http::response([], 404);
    }

    private function form(string $token, array $override = []): array
    {
        return array_replace_recursive([
            'submit_token' => $token, 'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '0532 123 45 67', 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Odunpazarı')->value('id'), 'neighborhood' => 'Arifiye', 'address_line' => 'Hamamyolu Cad. No 12'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'card', 'consent' => '1',
        ], $override);
    }

    private function checkout(?string $token = null): TestResponse
    {
        return $this->post(self::HOST.'/odeme', $this->form($token ?? (string) Str::uuid()));
    }

    private function initializeCalls(): int
    {
        return collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'initialize'))->count();
    }

    public function test_double_submit_with_same_token_opens_one_order_and_returns_to_its_payment_page(): void
    {
        $token = (string) Str::uuid();
        $this->checkout($token)->assertRedirect('https://sandbox.iyzico.test/odeme/1');
        $this->checkout($token)->assertRedirect('https://sandbox.iyzico.test/odeme/1'); // çift tıklama / geri tuşu

        $this->assertSame(1, StoreOrder::count());
        $this->assertSame(1, $this->initializeCalls());
        $this->assertSame([$this->pen->id => 8], app(StoreStockService::class)->available([$this->pen->id]), 'stok bir kez ayrıldı');
        $this->get(self::HOST.'/odeme')->assertSee('name="submit_token"', false);
    }

    public function test_new_attempt_with_same_cart_closes_unpaid_order_and_stock_is_held_once(): void
    {
        $this->checkout();
        $first = StoreOrder::firstOrFail();
        $this->checkout()->assertRedirect('https://sandbox.iyzico.test/odeme/2');

        $this->assertSame(['payment_failed', 'expired'], [$first->fresh()->status, $first->payments()->value('status')]);
        $this->assertSame('pending_payment', StoreOrder::latest('id')->value('status'));
        $this->assertSame([$this->pen->id => 8], app(StoreStockService::class)->available([$this->pen->id]));
    }

    public function test_new_attempt_after_first_was_actually_paid_takes_no_second_payment(): void
    {
        $this->checkout();
        $first = StoreOrder::firstOrFail();
        $this->paid['tok-1'] = true; // müşteri ödedi ama dönüş gelmedi (internet koptu)

        $this->checkout()->assertRedirect(route('store.order', ['orderNo' => $first->order_no, 'token' => $first->access_token]))
            ->assertSessionHas('status', 'Bu sepet için ödemeniz zaten alınmış; yeni ödeme yapılmadı.');
        $this->assertSame(1, StoreOrder::count());
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame(1, $this->initializeCalls());
    }

    public function test_late_payment_of_closed_attempt_is_refunded_automatically(): void
    {
        $this->checkout();
        $first = StoreOrder::firstOrFail();
        $this->checkout();
        $second = StoreOrder::latest('id')->firstOrFail();

        // İkinci deneme ödenir, ardından eski sekmedeki ilk deneme de ödenir: ikisi birden çekilmiş olur.
        $this->paid['tok-2'] = true;
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-2']);
        $this->paid['tok-1'] = true;
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-1']);

        $this->assertSame('paid', $second->fresh()->status);
        $this->assertSame('payment_failed', $first->fresh()->status);
        $payment = $first->payments()->firstOrFail();
        $this->assertSame('auto_refunded', $payment->status);
        $this->assertSame([['tx-tok-1-'.$first->items()->value('id'), '200.00']], $this->refunds);
        $this->assertSame('0.00', (string) $first->fresh()->refunded_total, 'iptal siparişin tutarları değişmez');
        $this->assertTrue(AuditLog::where('action', 'store.payment_auto_refunded')->exists());
        $this->assertSame(8, $this->pen->fresh()->stock_quantity, 'stok yalnızca ödenen sipariş için düştü');

        $admin = StoreAdmin::create(['name' => 'Y', 'email' => 'y@magaza.local', 'password' => self::PASSWORD, 'is_super' => true]);
        $this->actingAs($admin, 'store_admin')->get(route('store_admin.orders.show', $first))->assertSee('fazla ödeme, otomatik iade edildi');
    }

    public function test_payment_arriving_after_expiry_is_refunded_and_refund_failure_is_flagged(): void
    {
        $this->checkout();
        $order = StoreOrder::firstOrFail();
        $this->travel(36)->minutes();
        $this->artisan('store:expire-pending-payments')->assertSuccessful();
        $this->assertSame('payment_failed', $order->fresh()->status);

        $this->refundMode = 'fail';
        $this->paid['tok-1'] = true;
        $this->postJson(self::HOST.'/odeme/iyzico/bildirim', ['token' => 'tok-1'])->assertOk();
        $payment = $order->payments()->firstOrFail();
        $this->assertSame('refund_failed', $payment->status);
        $this->assertStringContainsString('iade EDİLEMEDİ', $payment->error_message);
        $this->assertSame('payment_failed', $order->fresh()->status);
    }

    public function test_pending_order_page_checks_result_live(): void
    {
        $this->checkout();
        $order = StoreOrder::firstOrFail();
        $url = route('store.order.payment-status', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
        $this->get(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]))->assertSee('Ödemeniz kontrol ediliyor')->assertSee('paymentWatcher', false);

        $this->getJson($url)->assertOk()->assertJson(['status' => 'pending_payment']);
        $this->paid['tok-1'] = true;
        $this->travel(15)->seconds();
        $this->getJson($url)->assertOk()->assertJson(['status' => 'paid']);
        $this->getJson(route('store.order.payment-status', ['orderNo' => $order->order_no, 'token' => str_repeat('x', 40)]))->assertNotFound();
    }

    public function test_unpaid_card_orders_are_limited_per_visitor(): void
    {
        foreach (range(1, 3) as $i) {
            $this->newCart(1);
            $this->checkout()->assertRedirect("https://sandbox.iyzico.test/odeme/{$i}");
        }
        $this->newCart(1);
        $this->checkout()->assertSessionHasErrors('payment_method');
        $this->assertSame(3, StoreOrder::where('status', 'pending_payment')->count());
    }

    public function test_signature_is_monitored_and_can_be_enforced(): void
    {
        // İzle: imza yok / tutmuyor → ödeme kabul edilir, uyarı kaydı düşer.
        $this->signature = 'wrong';
        $this->checkout();
        $this->paid['tok-1'] = true;
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-1']);
        $this->assertSame('paid', StoreOrder::value('status'));
        $this->assertTrue(AuditLog::where('action', 'store.payment_signature_mismatch')->exists());

        // Zorunlu: tutmayan imzayla ödeme kabul edilmez; doğru imzayla kabul edilir.
        StoreSetting::put(['iyzico_signature' => 'enforce']);
        $this->newCart();
        $this->checkout();
        $order = StoreOrder::latest('id')->firstOrFail();
        $this->paid['tok-2'] = true;
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-2']);
        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->signature = 'valid';
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-2']);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_signature_uses_prices_as_iyzico_library_does(): void
    {
        // iyzico tutarları "3.0" / "12.50" diye yazar; imza çözülmüş sayının metniyle ("3", "12.5") hesaplanır.
        $sign = fn (string $paid, string $price) => hash_hmac('sha256', "SUCCESS:p1:TRY:YB1:YB1:{$paid}:{$price}:tok", 'gizli');
        $api = new IyzicoApi('anahtar', 'gizli', 'live');
        foreach ([['3.0', '3.0', '3', '3'], ['12.50', '10.0', '12.5', '10']] as [$rawPaid, $rawPrice, $paid, $price]) {
            $this->rawBody = ('{"status":"success","paymentStatus":"SUCCESS","paymentId":"p1","currency":"TRY","basketId":"YB1","conversationId":"YB1",'
                .'"paidPrice":'.$rawPaid.',"price":'.$rawPrice.',"token":"tok","signature":"'.$sign($paid, $price).'"}');
            $result = $api->retrieveCheckoutForm('tok', 'YB1');
            $this->assertTrue($api->checkoutSignatureValid($result, 'tok'), "{$rawPaid} / {$rawPrice}");
            $this->assertFalse($api->checkoutSignatureValid(['signature' => $sign($rawPaid, $rawPrice)] + $result, 'tok'));
        }
    }

    public function test_uncertain_refund_blocks_retry_until_admin_resolves_it(): void
    {
        $this->checkout();
        $order = StoreOrder::firstOrFail();
        $this->paid['tok-1'] = true;
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-1']);
        $admin = StoreAdmin::create(['name' => 'Y', 'email' => 'y@magaza.local', 'password' => self::PASSWORD, 'is_super' => true]);
        $this->actingAs($admin, 'store_admin');

        $this->refundMode = 'unreachable';
        $this->post(route('store_admin.orders.refund', $order), ['amount' => '50', 'refund_reason' => 'Eksik ürün'])->assertSessionHasErrors('order');
        $refund = StoreRefund::sole();
        $this->assertSame(['uncertain', '0.00'], [$refund->status, (string) $order->fresh()->refunded_total]);
        $this->get(route('store_admin.orders.show', $order))->assertSee('Sonucu belirsiz')->assertSee('Yapıldı');

        $this->refundMode = 'success';
        $this->post(route('store_admin.orders.refund', $order), ['amount' => '50', 'refund_reason' => 'Tekrar'])->assertSessionHasErrors('order');
        $this->assertSame([], $this->refunds, 'belirsiz iade varken yeniden denenmez');

        $this->post(route('store_admin.orders.refund-resolve', [$order, $refund]), ['result' => 'done'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(['50.00', 'partially_refunded'], [(string) $order->refunded_total, $order->payment_status]);
        $this->assertSame('resolved', $refund->fresh()->status);
        $payment = StorePayment::where('order_id', $order->id)->where('status', 'succeeded')->firstOrFail();
        $this->assertSame('50.00', $payment->item_transactions[0]['refunded']);
    }

    public function test_items_added_to_cart_during_payment_are_kept_and_test_mode_is_flagged(): void
    {
        $this->checkout();
        $order = StoreOrder::firstOrFail();
        $other = StoreProduct::create(['name' => 'Defter', 'slug' => 'defter', 'is_published' => true]);
        $notebook = $other->variants()->create(['sku' => 'DEF-1', 'price' => '30.00', 'vat_rate' => '10']);
        app(StoreStockService::class)->receive($notebook, 5);
        app(CartService::class)->add($this->cart, $notebook->id, 1); // ödeme sayfasındayken başka sekmede eklendi

        $this->paid['tok-1'] = true;
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-1']);
        $this->assertSame([$notebook->id], $this->cart->items()->pluck('variant_id')->all());

        $admin = StoreAdmin::create(['name' => 'Y', 'email' => 'y@magaza.local', 'password' => self::PASSWORD, 'is_super' => true]);
        $this->actingAs($admin, 'store_admin');
        $this->get(route('store_admin.orders.show', $order))->assertSee('TEST ÖDEMESİ')->assertSee('Kartla ödeme TEST modunda');
        StoreSetting::put(['iyzico_mode' => 'live']);
        $this->get(route('store_admin.orders.index'))->assertDontSee('Kartla ödeme TEST modunda');
    }
}
