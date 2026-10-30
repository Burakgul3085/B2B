<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\CheckoutService;
use App\Domain\Store\Returns\ReturnService;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreRefund;
use App\Models\Store\StoreReturn;
use App\Models\Store\StoreSetting;
use App\Models\Store\StoreStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mağaza M7: kargo firmaları, kartla ödenmiş siparişin iptali (iyzico iadesi), iade talepleri, para iadesi, fatura. */
class StoreFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreProductVariant $pen;

    private StoreProductVariant $notebook;

    /** @var list<string> iyzico'da reddedilecek işlem kimlikleri */
    private array $failTransactions = [];

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'card_enabled' => '1', 'iyzico_mode' => 'sandbox', 'shipping_cargo_fee' => '50.00']);
        StoreSetting::putSecret('iyzico_api_key', 'sandbox-api-key');
        StoreSetting::putSecret('iyzico_secret_key', 'sandbox-secret-key');

        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $this->pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        $other = StoreProduct::create(['name' => 'Defter', 'slug' => 'defter', 'is_published' => true]);
        $this->notebook = $other->variants()->create(['sku' => 'DEF-1', 'price' => '30.00', 'vat_rate' => '10']);
        app(StoreStockService::class)->receive($this->pen, 10);
        app(StoreStockService::class)->receive($this->notebook, 10);

        Http::fake(['*' => fn (HttpRequest $r) => $this->iyzico($r)]);
        $this->actingAs(StoreAdmin::create(['name' => 'Sipariş Sorumlusu', 'email' => 's@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    /** Sahte iyzico: ödeme formu, sonuç (kalem bazında işlem kimlikleriyle) ve iade. */
    private function iyzico(HttpRequest $r)
    {
        $order = StoreOrder::latest('id')->first();
        if (str_contains($r->url(), 'initialize')) {
            return Http::response(['status' => 'success', 'token' => 'tok-'.$order->id, 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme']);
        }
        if (str_contains($r->url(), 'detail')) {
            $items = $order->items()->get()->map(fn ($i) => ['itemId' => (string) $i->id, 'paymentTransactionId' => 'tx-'.$i->id, 'price' => (float) $i->line_total, 'paidPrice' => (float) $i->line_total])->all();
            if (bccomp((string) $order->shipping_fee, '0', 2) > 0) {
                $items[] = ['itemId' => 'teslimat', 'paymentTransactionId' => 'tx-ship-'.$order->id, 'price' => (float) $order->shipping_fee, 'paidPrice' => (float) $order->shipping_fee];
            }

            return Http::response(['status' => 'success', 'paymentStatus' => 'SUCCESS', 'paymentId' => 'p-'.$order->id, 'basketId' => $order->order_no,
                'price' => (float) $order->total, 'paidPrice' => (float) $order->total, 'fraudStatus' => 1, 'itemTransactions' => $items]);
        }
        if (str_contains($r->url(), '/payment/refund')) {
            return in_array($r['paymentTransactionId'], $this->failTransactions, true)
                ? Http::response(['status' => 'failure', 'errorCode' => '10', 'errorMessage' => 'Banka iadeyi reddetti'])
                : Http::response(['status' => 'success', 'paymentTransactionId' => $r['paymentTransactionId'], 'price' => $r['price']]);
        }

        return Http::response([], 404);
    }

    /** Kartla ödenmiş kargo siparişi: 2 kalem (2 × 100 + 1 × 30) + 50 kargo = 280. */
    private function paidCardOrder(): StoreOrder
    {
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $this->pen->id, 2);
        $carts->add($cart, $this->notebook->id, 1);
        $this->withCookie(CurrentCart::COOKIE, $cart->token);

        $this->post(self::HOST.'/odeme', [
            'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567', 'province_id' => 6,
                'district_id' => District::where('province_id', 6)->where('name', 'Çankaya')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'cargo', 'payment_method' => 'card', 'consent' => '1',
        ])->assertRedirect('https://sandbox.iyzico.test/odeme');
        $order = StoreOrder::latest('id')->firstOrFail();
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-'.$order->id]);

        $order->refresh();
        $this->assertSame(['paid', '280.00'], [$order->status, $order->total]);

        return $order;
    }

    public function test_iyzico_webhook_completes_payment_even_if_customer_never_returns(): void
    {
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $this->pen->id, 1);
        $this->withCookie(CurrentCart::COOKIE, $cart->token);
        $this->post(self::HOST.'/odeme', [
            'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567', 'province_id' => 6,
                'district_id' => District::where('province_id', 6)->where('name', 'Çankaya')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'cargo', 'payment_method' => 'card', 'consent' => '1',
        ])->assertRedirect('https://sandbox.iyzico.test/odeme');
        $order = StoreOrder::latest('id')->firstOrFail();
        $this->assertSame('pending_payment', $order->status);

        // Müşteri geri dönmeden iyzico sunucudan bildirir (çerez, oturum, CSRF yok). Bilinmeyen belirteçler sessizce yok sayılır.
        $this->flushSession();
        $this->postJson(self::HOST.'/odeme/iyzico/bildirim', ['iyziEventType' => 'CHECKOUT_FORM_AUTH', 'token' => 'yok-boyle'])->assertOk();
        $this->postJson(self::HOST.'/odeme/iyzico/bildirim', ['token' => ['dizi']])->assertOk();
        $this->assertSame('pending_payment', $order->fresh()->status);

        $this->postJson(self::HOST.'/odeme/iyzico/bildirim', ['iyziEventType' => 'CHECKOUT_FORM_AUTH', 'token' => 'tok-'.$order->id, 'status' => 'SUCCESS'])
            ->assertOk()->assertJson(['status' => 'ok']);
        $this->assertSame(['paid', 9], [$order->fresh()->status, $this->pen->fresh()->stock_quantity]);

        // Sonradan gelen geri dönüş veya tekrar eden bildirim ikinci kez işlemez.
        $this->postJson(self::HOST.'/odeme/iyzico/bildirim', ['token' => 'tok-'.$order->id])->assertOk();
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-'.$order->id]);
        $this->assertSame(9, $this->pen->fresh()->stock_quantity);
        $this->assertSame(1, StoreStockMovement::where('reference_id', $order->id)->where('type', 'sale')->count());
    }

    private function deliver(StoreOrder $order): void
    {
        $carrier = StoreCarrier::where('name', 'Aras Kargo')->firstOrFail();
        $this->post(route('store_admin.orders.ship', $order), ['carrier_id' => $carrier->id, 'tracking_number' => 'ARS123'])->assertSessionHasNoErrors();
        $this->post(route('store_admin.orders.deliver', $order))->assertSessionHasNoErrors();
    }

    private function refundRequests(): array
    {
        return collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), '/payment/refund'))
            ->map(fn ($pair) => [$pair[0]['paymentTransactionId'], $pair[0]['price']])->values()->all();
    }

    public function test_carriers_and_tracking_link_on_shipment(): void
    {
        $this->get(route('store_admin.carriers.index'))->assertOk()->assertSee('Yurtiçi Kargo');
        // {takip} olmadan da girilebilir (numarayı adresten almayan firmalarda takip sayfası açılır); https zorunlu.
        $this->post(route('store_admin.carriers.store'), ['name' => 'Hızlı Kargo', 'tracking_url' => 'ornek.com/takip'])->assertSessionHasErrors('tracking_url');
        $this->post(route('store_admin.carriers.store'), ['name' => 'Hızlı Kargo', 'tracking_url' => 'http://ornek.com/{takip}'])->assertSessionHasErrors('tracking_url');
        $this->post(route('store_admin.carriers.store'), ['name' => 'Aras Kargo'])->assertSessionHasErrors('name');
        $this->post(route('store_admin.carriers.store'), ['name' => 'Hızlı Kargo', 'tracking_url' => 'https://ornek.com/sorgu?no={takip}'])->assertSessionHasNoErrors();
        $carrier = StoreCarrier::where('name', 'Hızlı Kargo')->firstOrFail();

        $order = $this->paidCardOrder();
        $this->get(route('store_admin.orders.show', $order))->assertOk()->assertSee('Hızlı Kargo')->assertSee('İptal et ve ödemeyi iade et');
        $this->post(route('store_admin.orders.ship', $order), ['tracking_number' => 'X1'])->assertSessionHasErrors('order');

        $this->put(route('store_admin.carriers.update', $carrier), ['name' => 'Hızlı Kargo', 'tracking_url' => $carrier->tracking_url, 'is_active' => '0']);
        $this->post(route('store_admin.orders.ship', $order), ['carrier_id' => $carrier->id, 'tracking_number' => 'X1'])->assertSessionHasErrors('order'); // pasif firma
        $this->put(route('store_admin.carriers.update', $carrier), ['name' => 'Hızlı Kargo', 'tracking_url' => $carrier->tracking_url, 'is_active' => '1']);
        $this->post(route('store_admin.orders.ship', $order), ['carrier_id' => $carrier->id, 'tracking_number' => 'AB 12/3'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(['shipped', 'Hızlı Kargo', 'https://ornek.com/sorgu?no=AB%2012%2F3'], [$order->status, $order->carrier, $order->tracking_url]);
        $this->get(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]))->assertOk()->assertSee('Kargom nerede')
            ->assertSee('https://ornek.com/sorgu?no=AB%2012%2F3', false);

        // Firma silinse de siparişteki kopya kalır.
        $this->delete(route('store_admin.carriers.destroy', $carrier))->assertSessionHasNoErrors();
        $this->assertSame(['Hızlı Kargo', null], [$order->fresh()->carrier, $order->fresh()->carrier_id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.carrier_deleted']);
    }

    public function test_cancelling_a_card_paid_order_refunds_every_transaction(): void
    {
        $order = $this->paidCardOrder();
        $payment = $order->payments()->where('status', 'succeeded')->firstOrFail();
        $this->assertCount(3, $payment->item_transactions, 'iki ürün + kargo işlemi kaydedildi');
        $this->assertSame([8, 9], [$this->pen->fresh()->stock_quantity, $this->notebook->fresh()->stock_quantity]);

        $this->post(route('store_admin.orders.cancel', $order), ['reason' => 'Müşteri vazgeçti'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(['cancelled', 'refunded', '280.00'], [$order->status, $order->payment_status, $order->refunded_total]);
        $items = $order->items()->get();
        $this->assertEqualsCanonicalizing([['tx-'.$items[0]->id, '200.00'], ['tx-'.$items[1]->id, '30.00'], ['tx-ship-'.$order->id, '50.00']], $this->refundRequests());
        $this->assertSame([10, 10], [$this->pen->fresh()->stock_quantity, $this->notebook->fresh()->stock_quantity]);
        $this->assertSame(['succeeded', 'iyzico', '280.00'], [StoreRefund::sole()->status, StoreRefund::sole()->method, StoreRefund::sole()->amount]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.refund_succeeded']);
    }

    public function test_failed_refund_keeps_order_open_and_retry_refunds_only_the_rest(): void
    {
        $order = $this->paidCardOrder();
        $this->failTransactions = ['tx-ship-'.$order->id];

        $this->post(route('store_admin.orders.cancel', $order), ['reason' => 'Stok yok'])->assertSessionHasErrors('order');
        $order->refresh();
        $this->assertSame(['paid', 'partially_refunded', '230.00'], [$order->status, $order->payment_status, $order->refunded_total]);
        $this->assertSame(['succeeded', 'failed'], StoreRefund::orderBy('id')->pluck('status')->all());
        $this->assertSame([8, 9], [$this->pen->fresh()->stock_quantity, $this->notebook->fresh()->stock_quantity], 'iptal edilmedi, stok değişmedi');

        $this->failTransactions = [];
        $this->post(route('store_admin.orders.cancel', $order), ['reason' => 'Stok yok'])->assertSessionHasNoErrors();
        $requests = $this->refundRequests();
        $this->assertSame(['tx-ship-'.$order->id, '50.00'], end($requests));
        $this->assertCount(4, $requests, 'ilk denemede 3 çağrı, tekrarda yalnızca başarısız olan');
        $this->assertSame(['cancelled', 'refunded', '280.00'], [$order->fresh()->status, $order->fresh()->payment_status, $order->fresh()->refunded_total]);
    }

    public function test_paid_order_without_stock_is_cancelled_without_phantom_restock(): void
    {
        $order = $this->paidCardOrder();
        // Ödeme süresinde stok tükendiği için satış hareketi oluşmamış sipariş (markPaid "stok yetersiz" yolu).
        DB::table('store_stock_movements')->where('reference_type', 'store_order')->where('reference_id', $order->id)->delete();
        $before = [$this->pen->fresh()->stock_quantity, $this->notebook->fresh()->stock_quantity];

        $this->post(route('store_admin.orders.cancel', $order), ['reason' => 'Stok yok'])->assertSessionHasNoErrors();
        $this->assertSame($before, [$this->pen->fresh()->stock_quantity, $this->notebook->fresh()->stock_quantity]);
    }

    public function test_card_return_flow_partial_then_full_with_shipping_fee(): void
    {
        $order = $this->paidCardOrder();
        $orderUrl = route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
        $returnUrl = route('store.order.return', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
        [$penItem, $noteItem] = $order->items()->get()->all();

        $this->post($returnUrl, ['items' => [$penItem->id => 1], 'reason' => 'changed_mind'])->assertSessionHasErrors('return'); // teslim edilmedi
        $this->deliver($order);
        $this->get($orderUrl)->assertOk()->assertSee('İade talebi oluştur');

        $this->post($returnUrl, ['items' => [$penItem->id => 3], 'reason' => 'changed_mind'])->assertSessionHasErrors('items');
        $this->post($returnUrl, ['items' => [$penItem->id => 0], 'reason' => 'changed_mind'])->assertSessionHasErrors('items');
        $this->post($returnUrl, ['items' => [$penItem->id => 2], 'reason' => 'yok'])->assertSessionHasErrors('reason');
        $this->post(route('store.order.return', ['orderNo' => $order->order_no, 'token' => str_repeat('x', 40)]), ['items' => [$penItem->id => 1], 'reason' => 'damaged'])->assertNotFound();

        $this->post($returnUrl, ['items' => [$penItem->id => 2], 'reason' => 'damaged', 'note' => 'Uçları kırık', 'photos' => [UploadedFile::fake()->image('kirik.jpg', 800, 600)]])->assertSessionHasNoErrors();
        $return = StoreReturn::sole();
        $this->assertSame(['requested', 'IA'.(100000 + $return->id)], [$return->status, $return->return_no]);
        $this->post($returnUrl, ['items' => [$noteItem->id => 1], 'reason' => 'changed_mind'])->assertSessionHasErrors('return'); // açık talep var

        // Yönetim: para iadesi teslim almadan yapılamaz; onay → teslim al (1 sağlam) → iade.
        $this->get(route('store_admin.returns.index', ['status' => 'requested']))->assertOk()->assertSee($return->return_no);
        $this->post(route('store_admin.returns.refund', $return), ['amount' => '200'])->assertSessionHasErrors('return');
        $this->post(route('store_admin.returns.approve', $return), ['note' => 'Aras Kargo ile anlaşmalı gönderin'])->assertSessionHasNoErrors();
        $this->post(route('store_admin.returns.receive', $return), ['resellable' => [$return->items()->value('id') => 1]])->assertSessionHasNoErrors();
        $this->assertSame(9, $this->pen->fresh()->stock_quantity, 'yalnızca sağlam adet stoğa döndü');

        $this->get(route('store_admin.returns.show', $return))->assertOk()->assertSee('Karta iade et')->assertSee('200,00');
        $this->post(route('store_admin.returns.refund', $return), ['amount' => '250,00'])->assertSessionHasErrors('return'); // kargo dahil değil
        $this->post(route('store_admin.returns.refund', $return), ['amount' => '200,00'])->assertSessionHasNoErrors();
        $this->assertSame([['tx-'.$penItem->id, '200.00']], $this->refundRequests());
        $order->refresh();
        $this->assertSame(['delivered', 'partially_refunded', '200.00'], [$order->status, $order->payment_status, $order->refunded_total]);
        $this->assertSame(['refunded', '200.00'], [$return->fresh()->status, $return->fresh()->refund_amount]);
        $this->post(route('store_admin.returns.refund', $return), ['amount' => '1'])->assertSessionHasErrors('return'); // iki kez ödenmez

        // Kalan ürün de iade edilince kargo ücreti de iade edilir.
        $this->post($returnUrl, ['items' => [$noteItem->id => 1], 'reason' => 'changed_mind'])->assertSessionHasNoErrors();
        $second = StoreReturn::latest('id')->firstOrFail();
        $this->post(route('store_admin.returns.approve', $second))->assertSessionHasNoErrors();
        $this->post(route('store_admin.returns.receive', $second), ['resellable' => [$second->items()->value('id') => 1]])->assertSessionHasNoErrors();
        $this->assertSame('80.00', app(ReturnService::class)->maxRefund($second->fresh()));
        $this->post(route('store_admin.returns.refund', $second), ['amount' => '80'])->assertSessionHasNoErrors();
        $this->assertSame(['refunded', '280.00'], [$order->fresh()->payment_status, $order->fresh()->refunded_total]);
        $this->get($orderUrl)->assertOk()->assertDontSee('İade talebi oluştur')->assertSee($second->return_no);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.return_refunded', 'entity_id' => $second->id]);
    }

    public function test_return_rejection_frees_quantities_and_window_closes(): void
    {
        $order = $this->paidCardOrder();
        $this->deliver($order);
        $returnUrl = route('store.order.return', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
        $penItem = $order->items()->firstOrFail();

        $this->post($returnUrl, ['items' => [$penItem->id => 2], 'reason' => 'changed_mind']);
        $return = StoreReturn::sole();
        $this->post(route('store_admin.returns.reject', $return), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('store_admin.returns.reject', $return), ['reason' => 'Ambalaj açılmış'])->assertSessionHasNoErrors();
        $this->assertSame(2, app(ReturnService::class)->returnableQuantities($order)[$penItem->id], 'reddedilen adet yeniden istenebilir');

        $order->forceFill(['delivered_at' => now()->subDays(15)])->save();
        $this->post($returnUrl, ['items' => [$penItem->id => 1], 'reason' => 'changed_mind'])->assertSessionHasErrors('return');
        StoreSetting::put(['return_days' => '30']);
        $this->post($returnUrl, ['items' => [$penItem->id => 1], 'reason' => 'changed_mind'])->assertSessionHasNoErrors();
    }

    public function test_cod_return_requires_valid_iban_and_manual_refund_note(): void
    {
        StoreSetting::put(['cod_enabled' => '1', 'cod_first_order_approval' => '0']);
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $this->pen->id, 1);
        $checkout = app(CheckoutService::class);
        $order = $checkout->place($checkout->validate([
            'email' => 'ali@ornek.com',
            'address' => ['first_name' => 'Ali', 'last_name' => 'Kaya', 'phone' => '05329998877', 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'İki Eylül Cad. No 1'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
        ], null, $carts->summary($cart)), null, $cart, '127.0.0.1', 'test');
        $this->post(route('store_admin.orders.ship', $order));
        $this->post(route('store_admin.orders.deliver', $order), ['collected_method' => 'cash']);

        $url = route('store.order.return', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
        $item = $order->items()->firstOrFail();
        $this->post($url, ['items' => [$item->id => 1], 'reason' => 'changed_mind'])->assertSessionHasErrors(['iban', 'account_name']);
        $this->post($url, ['items' => [$item->id => 1], 'reason' => 'changed_mind', 'iban' => 'TR33 0006 1005 1978 6457 8413 27', 'account_name' => 'Ali Kaya'])
            ->assertSessionHasErrors('iban'); // kontrol hanesi tutmuyor
        $this->post($url, ['items' => [$item->id => 1], 'reason' => 'changed_mind', 'iban' => 'tr33 0006 1005 1978 6457 8413 26', 'account_name' => 'Ali Kaya'])
            ->assertSessionHasNoErrors();

        $return = StoreReturn::sole();
        $this->assertSame('TR330006100519786457841326', $return->refund_iban);
        $this->post(route('store_admin.returns.approve', $return));
        $this->post(route('store_admin.returns.receive', $return), ['resellable' => [$return->items()->value('id') => 1]]);
        $this->get(route('store_admin.returns.show', $return))->assertSee('TR33 0006 1005 1978 6457 8413 26');
        $this->post(route('store_admin.returns.refund', $return), ['amount' => '100'])->assertSessionHasErrors('return'); // havale bilgisi zorunlu
        $this->post(route('store_admin.returns.refund', $return), ['amount' => '100', 'note' => '21.10.2026 EFT dekont 555'])->assertSessionHasNoErrors();

        $this->assertSame([], $this->refundRequests(), 'kapıda ödemede iyzico çağrılmaz');
        $this->assertSame(['manual', '100.00'], [StoreRefund::sole()->method, StoreRefund::sole()->amount]);
        $this->assertSame(['refunded', '100.00'], [$order->fresh()->payment_status, $order->fresh()->refunded_total]);
    }

    public function test_customer_cancels_before_shipping_card_refund_and_transfer_request(): void
    {
        $order = $this->paidCardOrder();
        $cancelUrl = route('store.order.cancel', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
        $orderUrl = route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
        $this->get($orderUrl)->assertSee('Siparişi iptal et')->assertSee('kartınıza otomatik iade edilir');

        $this->post($cancelUrl, ['reason' => 'yok'])->assertSessionHasErrors('reason');
        $this->post(route('store.order.cancel', ['orderNo' => $order->order_no, 'token' => str_repeat('x', 40)]), ['reason' => 'changed_mind'])->assertNotFound();
        $this->post($cancelUrl, ['reason' => 'changed_mind', 'note' => 'Yanlışlıkla verdim'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(['cancelled', 'refunded'], [$order->status, $order->payment_status]);
        $this->assertSame('280.00', number_format(collect($this->refundRequests())->sum(fn ($r) => (float) $r[1]), 2, '.', ''));
        $this->assertSame([10, 10], [$this->pen->fresh()->stock_quantity, $this->notebook->fresh()->stock_quantity]);
        $this->assertStringContainsString('Müşteri iptali: Vazgeçtim', $order->cancel_reason);
        $this->get($orderUrl)->assertDontSee('Siparişi iptal et');

        // Kargoya verilmiş sipariş iptal edilemez.
        $shipped = $this->paidCardOrder();
        $this->post(route('store_admin.orders.ship', $shipped), ['carrier_id' => StoreCarrier::where('name', 'Aras Kargo')->value('id'), 'tracking_number' => 'ARS9']);
        $this->post(route('store.order.cancel', ['orderNo' => $shipped->order_no, 'token' => $shipped->access_token]), ['reason' => 'changed_mind'])->assertSessionHasErrors('cancel');
        $this->assertSame('shipped', $shipped->fresh()->status);

        // Havalesi alınmış sipariş: para elle döneceği için IBAN ile iptal talebi olur, sipariş açık kalır.
        $transfer = $this->paidCardOrder();
        $transfer->forceFill(['payment_method' => 'transfer'])->save();
        $url = route('store.order.cancel', ['orderNo' => $transfer->order_no, 'token' => $transfer->access_token]);
        $this->post($url, ['reason' => 'too_slow'])->assertSessionHasErrors('iban');
        $this->post($url, ['reason' => 'too_slow', 'iban' => 'TR33 0006 1005 1978 6457 8413 26', 'account_name' => 'Ayşe Yılmaz'])->assertSessionHasNoErrors();
        $transfer->refresh();
        $this->assertSame(['paid', 'TR330006100519786457841326'], [$transfer->status, $transfer->cancel_refund_iban]);
        $this->assertNotNull($transfer->cancel_requested_at);
        $this->post($url, ['reason' => 'too_slow', 'iban' => 'TR330006100519786457841326', 'account_name' => 'Ayşe Yılmaz'])->assertSessionHasErrors('cancel');
        $this->get(route('store_admin.orders.show', $transfer))->assertSee('Müşteri iptal istedi')->assertSee('TR33 0006 1005 1978 6457 8413 26');
    }

    public function test_return_photos_customer_shipping_and_one_step_refund(): void
    {
        Storage::fake('local');
        $order = $this->paidCardOrder();
        $this->deliver($order);
        $orderUrl = route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
        $returnUrl = route('store.order.return', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
        $penItem = $order->items()->firstOrFail();

        // Mağazadan kaynaklı nedende fotoğraf zorunlu; en fazla 5; resim olmayan dosya reddedilir.
        $this->post($returnUrl, ['items' => [$penItem->id => 2], 'reason' => 'wrong_item'])->assertSessionHasErrors('photos');
        $this->post($returnUrl, ['items' => [$penItem->id => 2], 'reason' => 'wrong_item', 'photos' => array_fill(0, 6, UploadedFile::fake()->image('a.jpg'))])->assertSessionHasErrors('photos');
        $this->post($returnUrl, ['items' => [$penItem->id => 2], 'reason' => 'wrong_item', 'photos' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('photos.0');
        $this->post($returnUrl, ['items' => [$penItem->id => 2], 'reason' => 'wrong_item', 'photos' => [UploadedFile::fake()->image('a.jpg', 1200, 900), UploadedFile::fake()->image('b.png', 400, 300)]])
            ->assertSessionHasNoErrors();
        $return = StoreReturn::sole();
        $this->assertSame(2, $return->photos()->count());
        Storage::disk('local')->assertExists($return->photos()->value('path'));

        // Fotoğraflar: yönetimde ve müşteriye yalnızca imzalı bağlantıyla.
        $photo = $return->photos()->firstOrFail();
        $this->get(route('store_admin.returns.photo', [$return, $photo->id]))->assertOk();
        $this->get(self::HOST.'/iade-fotografi/'.$photo->id)->assertForbidden();
        $this->get($orderUrl)->assertOk()->assertSee('iade-fotografi/'.$photo->id.'?expires=', false);

        // Onay: müşteri talimatı ve kargo formunu görür; anlaşma kodu yokken ödediği kargo ücretini yazar (iadeye eklenir).
        $this->post(route('store_admin.returns.approve', $return))->assertSessionHasNoErrors();
        $shipUrl = route('store.order.return.ship', ['orderNo' => $order->order_no, 'token' => $order->access_token, 'returnNo' => $return->return_no]);
        $this->get($orderUrl)->assertSee('Kargoya verdim')->assertSee('gönderici ödemeli gönderin ve ödediğiniz kargo ücretini')
            ->assertSee($return->created_at->addDays(StoreReturn::SHIP_DAYS)->timezone('Europe/Istanbul')->format('d.m.Y'));
        $this->post($shipUrl, ['cargo_company' => 'Aras Kargo'])->assertSessionHasErrors('cargo_tracking');
        $this->post($shipUrl, ['cargo_company' => 'Aras Kargo', 'cargo_tracking' => 'ARS 777', 'cargo_fee' => '20,00'])->assertSessionHasNoErrors();
        $return->refresh();
        $this->assertSame(['shipped', 'ARS777', '20.00'], [$return->status, $return->cargo_tracking, (string) $return->cargo_fee]);
        $this->post($shipUrl, ['cargo_company' => 'Aras', 'cargo_tracking' => 'X1'])->assertSessionHasErrors('return'); // ikinci kez değil

        // Tek adım: teslim al (1 sağlam) ve iade et; ürün payı 200 + iade kargosu 20.
        $this->assertSame('220.00', app(ReturnService::class)->maxRefund($return));
        $this->get(route('store_admin.returns.show', $return))->assertOk()->assertSee('Teslim alındı, karta iade et')->assertSee('ARS777')->assertSee('220,00');
        $this->post(route('store_admin.returns.receive-refund', $return), ['resellable' => [$return->items()->value('id') => 1], 'amount' => '220,00'])
            ->assertSessionHasNoErrors();
        $return->refresh();
        $this->assertSame(['refunded', '220.00'], [$return->status, (string) $return->refund_amount]);
        $this->assertSame(9, $this->pen->fresh()->stock_quantity);
        $this->assertSame('220.00', (string) $order->fresh()->refunded_total);
    }

    public function test_unshipped_approved_returns_get_reminded_then_expire(): void
    {
        $order = $this->paidCardOrder();
        $this->deliver($order);
        $this->post(route('store.order.return', ['orderNo' => $order->order_no, 'token' => $order->access_token]), ['items' => [$order->items()->value('id') => 1], 'reason' => 'changed_mind']);
        $return = StoreReturn::sole();
        $this->post(route('store_admin.returns.approve', $return));
        // Anlaşma kodu tanımlıysa (sözleşme: bildirilen firmayla iade ücretsiz) müşteri kodu görür, kargo ücreti alanı çıkmaz.
        StoreSetting::put(['return_carrier' => 'Aras Kargo', 'return_code' => 'IADE-4455']);
        $this->get(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]))
            ->assertSee('Aras Kargo şubesine IADE-4455 iade anlaşma koduyla ücretsiz')->assertSee('başka bir kargo firmasıyla gönderirseniz kargo ücreti size aittir')
            ->assertDontSee('Ödediğiniz kargo ücreti');

        $this->travel(StoreReturn::REMIND_DAYS + 1)->days();
        $this->artisan('store:return-reminders')->expectsOutputToContain('1 hatırlatma, 0 kapanan')->assertSuccessful();
        $this->artisan('store:return-reminders')->expectsOutputToContain('0 hatırlatma, 0 kapanan'); // tek hatırlatma
        $this->assertNotNull($return->fresh()->reminded_at);

        $this->travel(StoreReturn::EXPIRE_DAYS)->days();
        $this->artisan('store:return-reminders')->expectsOutputToContain('0 hatırlatma, 1 kapanan');
        $this->assertSame('expired', $return->fresh()->status);
        $this->assertSame(2, app(ReturnService::class)->returnableQuantities($order)[$order->items()->value('id')], 'kapanan talebin adedi yeniden istenebilir');
    }

    public function test_invoice_upload_and_customer_download(): void
    {
        Storage::fake('local');
        $order = $this->paidCardOrder();
        $pdf = fn () => UploadedFile::fake()->createWithContent('fatura.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        $this->post(route('store_admin.orders.invoice.store', $order), ['invoice_no' => 'YBZ2026000000001', 'issued_on' => now('Europe/Istanbul')->toDateString()])->assertSessionHasErrors('file');
        $this->post(route('store_admin.orders.invoice.store', $order), ['invoice_no' => 'YBZ2026000000001', 'issued_on' => now('Europe/Istanbul')->toDateString(),
            'file' => UploadedFile::fake()->createWithContent('fatura.pdf', '<html>sahte</html>')])->assertSessionHasErrors();
        $this->post(route('store_admin.orders.invoice.store', $order), ['invoice_no' => 'YBZ 1', 'issued_on' => now('Europe/Istanbul')->toDateString(), 'file' => $pdf()])->assertSessionHasErrors('invoice_no');
        $this->post(route('store_admin.orders.invoice.store', $order), ['invoice_no' => 'ybz2026000000001', 'issued_on' => now('Europe/Istanbul')->toDateString(), 'file' => $pdf()])
            ->assertSessionHasNoErrors();

        $invoice = $order->invoice()->firstOrFail();
        $this->assertSame('YBZ2026000000001', $invoice->invoice_no);
        Storage::disk('local')->assertExists($invoice->file_path);
        $this->assertStringStartsWith('store/invoices/'.$order->id.'/', $invoice->file_path);

        $params = ['orderNo' => $order->order_no, 'token' => $order->access_token];
        $this->get(route('store.order', $params))->assertSee('Faturayı indir');
        $this->get(route('store.order.invoice', $params))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('store.order.invoice', ['orderNo' => $order->order_no, 'token' => str_repeat('a', 40)]))->assertNotFound();
        $this->get(route('store_admin.orders.invoice', $order))->assertOk();

        // Numara değişir, PDF boş bırakılırsa eskisi korunur; yeni PDF yüklenirse eskisi silinir.
        $oldPath = $invoice->file_path;
        $this->post(route('store_admin.orders.invoice.store', $order), ['invoice_no' => 'YBZ2026000000002', 'issued_on' => now('Europe/Istanbul')->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame([$oldPath, 'YBZ2026000000002'], [$invoice->fresh()->file_path, $invoice->fresh()->invoice_no]);
        $this->post(route('store_admin.orders.invoice.store', $order), ['invoice_no' => 'YBZ2026000000002', 'issued_on' => now('Europe/Istanbul')->toDateString(), 'file' => $pdf()]);
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.invoice_replaced']);
    }

    public function test_customer_cannot_reach_another_customers_invoice_or_return(): void
    {
        Storage::fake('local');
        $order = $this->paidCardOrder();
        $this->deliver($order);
        $customer = StoreCustomer::create(['first_name' => 'Başka', 'last_name' => 'Müşteri', 'email' => 'b@ornek.com', 'phone' => '05320000000', 'password' => self::PASSWORD]);
        auth('store_admin')->logout();
        $this->flushSession();
        $this->actingAs($customer, 'customer');

        $this->get(route('store.orders.invoice', $order->order_no))->assertNotFound();
        $this->post(route('store.orders.return', $order->order_no), ['items' => [$order->items()->value('id') => 1], 'reason' => 'changed_mind'])->assertNotFound();
        $this->assertSame(0, StoreReturn::count());
    }
}
