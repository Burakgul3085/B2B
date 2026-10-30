<?php

namespace Tests\Feature\Ordering;

use App\Domain\Auth\Area;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Domain\Payments\PaymentSettings;
use App\Models\AppNotification;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\CollectionEntry;
use App\Models\DealerRelationship;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * B2B ödemeleri: bayi yöntemi sepette seçer, toptancı onaylar, bayi ONAYLANAN tutarı öder (kart / havale / teslimde);
 * izinli bayi açık hesapla verir. Ödeme cariye tahsilat yazar; kartla ödenen sipariş iptalinde iyzico'dan iade edilir.
 */
class DealerPaymentTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private Business $business;

    private DealerRelationship $rel;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = $this->makeBusiness();
        $this->rel = $this->orderingDealer($this->business);
        $this->product = $this->makeProduct($this->business, ['dealer_price' => '10.00', 'name' => 'Defter A4']);
        $this->stockUp($this->business, $this->product, '100');
        $this->enablePayments();
    }

    private function enablePayments(array $overrides = []): void
    {
        BusinessSetting::where('business_id', $this->business->id)->update($overrides + [
            'payments_enabled' => true, 'card_enabled' => true, 'iyzico_mode' => 'sandbox', 'installments' => '1,3',
            'iyzico_api_key' => Crypt::encryptString('sandbox-api'), 'iyzico_secret_key' => Crypt::encryptString('sandbox-secret'),
            'transfer_enabled' => true, 'transfer_accounts' => json_encode([['bank' => 'Akbank', 'holder' => 'Yapboz Kırtasiye', 'iban' => 'TR280004609876543210987654']]),
        ]);
    }

    private function submit(string $qty, ?string $method): Order
    {
        return $this->inTenant($this->business, function () use ($qty, $method) {
            app(CartService::class)->add($this->rel, $this->product->id, $qty);

            return app(OrderService::class)->submit($this->rel, $this->rel->addresses()->value('id'), null, (string) Str::uuid(), $method)->fresh('items');
        });
    }

    private function approve(Order $order, ?string $qty = null): Order
    {
        $this->actingAs($this->business->user, 'business')
            ->post(route('business.orders.approve', $order), ['warehouse_id' => $this->defaultWarehouse($this->business)->id, 'qty' => [$order->items->first()->id => $qty ?? '10']])
            ->assertRedirect()->assertSessionHasNoErrors();

        return $this->inTenant($this->business, fn () => $order->fresh(['items', 'payments']));
    }

    private function fakeIyzico(string $price, string $status = 'SUCCESS', array $refund = ['status' => 'success', 'paymentTransactionId' => 'tx-55']): void
    {
        Http::fake([
            '*/checkoutform/initialize/*' => Http::response(['status' => 'success', 'token' => 'tok-1', 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme']),
            '*/checkoutform/auth/ecom/detail' => fn (HttpRequest $r) => Http::response([
                'status' => 'success', 'paymentStatus' => $status, 'basketId' => $r['conversationId'], 'price' => $price, 'paidPrice' => $price,
                'paymentId' => '9001', 'fraudStatus' => 1, 'installment' => 3, 'cardFamily' => 'Bonus', 'lastFourDigits' => '4242',
                'itemTransactions' => [['itemId' => 'siparis', 'paymentTransactionId' => 'tx-55', 'paidPrice' => $price]],
            ]),
            '*/payment/refund' => Http::response($refund),
        ]);
    }

    public function test_dealer_chooses_method_and_only_permitted_dealers_see_open_account_and_on_delivery(): void
    {
        $this->actingAs($this->rel->dealer->user, 'dealer');
        $this->inTenant($this->business, fn () => app(CartService::class)->add($this->rel, $this->product->id, '2'));
        $this->get(route('dealer.cart'))->assertOk()->assertSee('Ödeme yöntemi')->assertSee('Kredi / banka kartı')->assertSee('Havale / EFT')
            ->assertDontSee('Açık hesap (cari)')->assertDontSee('Teslimde ödeme');

        // İzin verilmeyen yöntem reddedilir; yöntem seçmeden sipariş verilemez.
        $this->post(route('dealer.cart.submit'), ['address_id' => $this->rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid(), 'payment_method' => 'open_account'])
            ->assertSessionHasErrors('cart');
        $this->post(route('dealer.cart.submit'), ['address_id' => $this->rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid()])
            ->assertSessionHasErrors('cart');

        $this->inTenant($this->business, fn () => $this->rel->update(['allow_open_account' => true, 'allow_on_delivery' => true]));
        $this->get(route('dealer.cart'))->assertSee('Açık hesap (cari)')->assertSee('Teslimde ödeme');
        $this->post(route('dealer.cart.submit'), ['address_id' => $this->rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid(), 'payment_method' => 'open_account'])
            ->assertRedirect();
        $order = $this->inTenant($this->business, fn () => Order::firstOrFail());
        $this->assertSame('open_account', $order->payment_method);

        // Açık hesap: onaydan sonra ödeme beklenmez, hazırlanabilir.
        $order = $this->approve($order, '2');
        $this->assertNull($order->payment_status);
        $this->post(route('business.orders.prepare', $order))->assertSessionHasNoErrors();
    }

    public function test_partial_approval_asks_for_approved_amount_and_highlights_changes(): void
    {
        $order = $this->submit('10', 'transfer');
        $this->assertSame('120.00', (string) $order->grand_total);

        $order = $this->approve($order, '6');
        $this->assertSame(['awaiting', '72.00', '120.00'], [$order->payment_status, (string) $order->grand_total, (string) $order->requested_total]);

        $mail = $this->inTenant($this->business, fn () => EmailLog::where('template_key', 'order_payment_due')->firstOrFail());
        $this->assertStringContainsString('₺72,00', $mail->subject);
        $this->assertStringContainsString('10 Adet istendi → 6 Adet onaylandı', json_encode($mail->payload, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('değişiklik yapıldı', json_encode($mail->payload, JSON_UNESCAPED_UNICODE));
        $this->assertTrue($this->inTenant($this->business, fn () => AppNotification::where('recipient_side', 'dealer')->where('title', 'like', '%₺72,00 ödeyin')->exists()));

        $this->actingAs($this->rel->dealer->user, 'dealer')->get(route('dealer.orders.show', $order))->assertOk()
            ->assertSee('Siparişinizde değişiklik yapıldı')->assertSee('Ödenecek tutar')->assertSee('₺72,00')->assertSee('₺120,00')
            ->assertSee('TR28 0004 6098 7654 3210 9876 54')->assertSee($order->order_no);

        // Havale gelmeden hazırlanamaz ve tamamlanamaz.
        $this->actingAs($this->business->user, 'business');
        $this->post(route('business.orders.prepare', $order))->assertSessionHasErrors('order');
        $this->post(route('business.orders.complete', $order))->assertSessionHasErrors('order');

        // Toptancı havaleyi kaydeder: cariye tahsilat, sipariş ödendi; onaydaki borçla bakiye sıfırlanır.
        $this->post(route('business.orders.payments.store', $order), ['method' => 'transfer', 'paid_on' => now('Europe/Istanbul')->toDateString(), 'note' => 'Dekont 12'])
            ->assertSessionHasNoErrors();
        $order = $this->inTenant($this->business, fn () => $order->fresh());
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('0.00', $this->balance($this->business, $this->rel));
        $this->assertSame('transfer', $this->inTenant($this->business, fn () => CollectionEntry::where('order_id', $order->id)->value('method')));
        $this->post(route('business.orders.prepare', $order))->assertSessionHasNoErrors();
        $this->post(route('business.orders.complete', $order))->assertSessionHasNoErrors();
    }

    public function test_card_payment_via_iyzico_records_collection_and_refunds_on_cancel(): void
    {
        $order = $this->approve($this->submit('10', 'card'));
        $this->fakeIyzico('120.00');

        $this->actingAs($this->rel->dealer->user, 'dealer')->post(route('dealer.orders.pay', $order))->assertRedirect('https://sandbox.iyzico.test/odeme');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'initialize') && $r['basketId'] === $order->order_no
            && $r['price'] === '120.00' && $r['enabledInstallments'] === [1, 3] && str_contains($r['callbackUrl'], '/bayi/odeme/iyzico/donus'));

        // iyzico geri dönüşü: başka siteden, çerezsiz; oturum açılmaz (bayinin oturum çerezi ezilmez).
        $this->flushSession();
        auth()->forgetGuards();
        $response = $this->post('/bayi/odeme/iyzico/donus', ['token' => 'tok-1']);
        $response->assertRedirect(route('dealer.orders.show', [$order, 'odeme' => 'sonuc']))->assertCookieMissing(Area::Dealer->cookieName());

        $order = $this->inTenant($this->business, fn () => $order->fresh('payments'));
        $this->assertSame('paid', $order->payment_status);
        $payment = $order->payments->first();
        $this->assertSame(['succeeded', 'tx-55', '4242', 3], [$payment->status, $payment->transaction_id, $payment->card_last_four, $payment->installment]);
        $this->assertSame('card_online', $this->inTenant($this->business, fn () => CollectionEntry::find($payment->collection_id)->method));
        $this->assertSame('0.00', $this->balance($this->business, $this->rel));
        $this->assertTrue($this->inTenant($this->business, fn () => EmailLog::where('template_key', 'order_paid')->exists()));

        // Aynı belirteç ikinci kez işlenmez.
        $this->post('/bayi/odeme/iyzico/donus', ['token' => 'tok-1']);
        $this->assertSame(1, $this->inTenant($this->business, fn () => CollectionEntry::count()));

        // Bayi ekranı: ödendi.
        $this->actingAs($this->rel->dealer->user, 'dealer')->get(route('dealer.orders.show', [$order, 'odeme' => 'sonuc']))
            ->assertSee('Ödemeniz alındı')->assertSee('**** 4242');

        // Toptancı iptal eder: kart iadesi iyzico'dan, tahsilat ve borç ters kayıtla; bakiye sıfır.
        $this->actingAs($this->business->user, 'business')->post(route('business.orders.cancel', $order), ['reason' => 'Stok sayım hatası'])->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/payment/refund') && $r['paymentTransactionId'] === 'tx-55' && $r['price'] === '120.00');
        $order = $this->inTenant($this->business, fn () => $order->fresh('payments'));
        $this->assertSame(['cancelled', 'refunded', 'refunded'], [$order->status, $order->payment_status, $order->payments->first()->status]);
        $this->assertSame('0.00', $this->balance($this->business, $this->rel));
    }

    public function test_failed_card_payment_keeps_order_awaiting_and_refund_failure_blocks_cancel(): void
    {
        $order = $this->approve($this->submit('10', 'card'));
        $this->fakeIyzico('120.00', 'FAILURE');
        $this->actingAs($this->rel->dealer->user, 'dealer')->post(route('dealer.orders.pay', $order));
        $this->post('/bayi/odeme/iyzico/donus', ['token' => 'tok-1']);
        $order = $this->inTenant($this->business, fn () => $order->fresh('payments'));
        $this->assertSame(['awaiting', 'failed'], [$order->payment_status, $order->payments->first()->status]);
        $this->actingAs($this->rel->dealer->user, 'dealer')->get(route('dealer.orders.show', [$order, 'odeme' => 'sonuc']))->assertSee('Ödeme alınamadı');

        // Tutarı tutmayan ödeme kabul edilmez.
        Http::fake([
            '*/checkoutform/initialize/*' => Http::response(['status' => 'success', 'token' => 'tok-2', 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme']),
            '*/checkoutform/auth/ecom/detail' => Http::response(['status' => 'success', 'paymentStatus' => 'SUCCESS', 'basketId' => $order->order_no, 'price' => '1.00', 'fraudStatus' => 1]),
        ]);
        $this->post(route('dealer.orders.pay', $order));
        $this->post('/bayi/odeme/iyzico/donus', ['token' => 'tok-2']);
        $this->assertSame('awaiting', $this->inTenant($this->business, fn () => $order->fresh()->payment_status));

        // Bayi yöntemi havaleye çevirebilir.
        $this->actingAs($this->rel->dealer->user, 'dealer')->post(route('dealer.orders.payment-method', $order), ['payment_method' => 'transfer'])->assertSessionHasNoErrors();
        $this->assertSame('transfer', $this->inTenant($this->business, fn () => $order->fresh()->payment_method));
        $this->post(route('dealer.orders.payment-method', $order), ['payment_method' => 'open_account'])->assertSessionHasErrors('payment');
    }

    public function test_refund_failure_keeps_paid_order_open(): void
    {
        $order = $this->approve($this->submit('10', 'card'));
        $this->fakeIyzico('120.00', refund: ['status' => 'failure', 'errorMessage' => 'İade süresi geçti']);
        $this->actingAs($this->rel->dealer->user, 'dealer')->post(route('dealer.orders.pay', $order));
        $this->post('/bayi/odeme/iyzico/donus', ['token' => 'tok-1']);

        $this->actingAs($this->business->user, 'business')->post(route('business.orders.cancel', $order), ['reason' => 'Vazgeçildi'])
            ->assertSessionHasErrors(['order' => 'Kart iadesi yapılamadı: İade süresi geçti. Sipariş iptal edilmedi.']);
        $order = $this->inTenant($this->business, fn () => $order->fresh());
        $this->assertSame(['approved', 'paid'], [$order->status, $order->payment_status]);
        $this->assertSame('0.00', $this->balance($this->business, $this->rel));
    }

    public function test_delivery_fee_is_added_and_recalculated_on_approval(): void
    {
        BusinessSetting::where('business_id', $this->business->id)->update(['delivery_fee' => '50.00', 'delivery_free_over' => '100.00']);

        $this->actingAs($this->rel->dealer->user, 'dealer');
        $this->inTenant($this->business, fn () => app(CartService::class)->add($this->rel, $this->product->id, '5'));
        $this->get(route('dealer.cart'))->assertSee('Teslimat ücreti')->assertSee('₺110,00');
        $this->inTenant($this->business, fn () => app(CartService::class)->add($this->rel, $this->product->id, '5'));

        $order = $this->inTenant($this->business, fn () => app(OrderService::class)->submit($this->rel, $this->rel->addresses()->value('id'), null, (string) Str::uuid(), 'transfer'));
        $this->assertSame(['0.00', '120.00'], [(string) $order->delivery_fee, (string) $order->grand_total]); // 100 ₺ ve üstü ücretsiz

        $order = $this->approve($this->inTenant($this->business, fn () => $order->fresh('items')), '5'); // onaylanan 60 ₺ → ücret eklenir
        $this->assertSame(['50.00', '110.00', '110.00'], [(string) $order->delivery_fee, (string) $order->grand_total, $this->balance($this->business, $this->rel)]);
    }

    public function test_payments_disabled_keeps_old_behaviour(): void
    {
        BusinessSetting::where('business_id', $this->business->id)->update(['payments_enabled' => false]);
        $this->actingAs($this->rel->dealer->user, 'dealer');
        $this->inTenant($this->business, fn () => app(CartService::class)->add($this->rel, $this->product->id, '1'));
        $this->get(route('dealer.cart'))->assertOk()->assertDontSee('Ödeme yöntemi');

        $order = $this->approve($this->submit('1', null), '2');
        $this->assertSame([null, null], [$order->payment_method, $order->payment_status]);
        $this->post(route('business.orders.prepare', $order))->assertSessionHasNoErrors();
    }

    public function test_payment_settings_page_encrypts_keys_and_validates(): void
    {
        BusinessSetting::where('business_id', $this->business->id)->update(['iyzico_api_key' => null, 'iyzico_secret_key' => null, 'card_enabled' => false]);
        $this->actingAs($this->business->user, 'business');
        $this->get(route('business.payments.edit'))->assertOk()->assertSee('Ödeme Ayarları')->assertSee('Kayıtlı değil.');

        $this->put(route('business.payments.update'), ['payments_enabled' => '1', 'card_enabled' => '1'])->assertSessionHasErrors('card_enabled');
        $this->put(route('business.payments.update'), ['payments_enabled' => '1', 'transfer_enabled' => '1', 'transfer_accounts' => [['holder' => 'X', 'iban' => 'TR00']]])
            ->assertSessionHasErrors('transfer_accounts.0.iban');

        $this->put(route('business.payments.update'), [
            'payments_enabled' => '1', 'card_enabled' => '1', 'iyzico_mode' => 'sandbox', 'iyzico_api_key' => 'api-123', 'iyzico_secret_key' => 'sec-456',
            'installments' => ['1', '6', '7'], 'delivery_fee' => '25,50', 'delivery_free_over' => '',
            'transfer_enabled' => '1', 'transfer_accounts' => [['holder' => 'Yapboz', 'iban' => 'TR28 0004 6098 7654 3210 9876 54']],
        ])->assertSessionHasNoErrors();

        $s = BusinessSetting::where('business_id', $this->business->id)->first();
        $this->assertNotSame('api-123', $s->getRawOriginal('iyzico_api_key'));
        $this->assertSame('api-123', Crypt::decryptString($s->iyzico_api_key));
        $this->assertSame(['1,6', '25.50', null, 'Akbank'], [$s->installments, (string) $s->delivery_fee, $s->delivery_free_over, $s->transfer_accounts[0]['bank']]);
        $this->get(route('business.payments.edit'))->assertDontSee('api-123')->assertDontSee('sec-456');
        $this->assertSame([1, 6], $this->inTenant($this->business, fn () => app(PaymentSettings::class)->installments()));
    }

    public function test_stale_card_payments_are_resolved_by_scheduler(): void
    {
        $order = $this->approve($this->submit('10', 'card'));
        $this->fakeIyzico('120.00');
        $this->actingAs($this->rel->dealer->user, 'dealer')->post(route('dealer.orders.pay', $order));
        $this->travel(31)->minutes();

        $this->artisan('b2b:expire-pending-payments')->assertSuccessful();
        $this->assertSame('paid', $this->inTenant($this->business, fn () => $order->fresh()->payment_status));
        $this->assertSame(0, $this->inTenant($this->business, fn () => OrderPayment::where('status', 'initialized')->count()));
    }
}
