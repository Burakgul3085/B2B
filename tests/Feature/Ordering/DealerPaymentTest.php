<?php

namespace Tests\Feature\Ordering;

use App\Domain\Auth\Area;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Domain\Payments\OrderPaymentService;
use App\Domain\Payments\PayLaterPolicy;
use App\Domain\Payments\PaymentSettings;
use App\Models\AppNotification;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Models\DealerRelationship;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderReturn;
use App\Models\OrderReturnRequest;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * B2B ödemeleri: bayi yöntemi sepette seçer, toptancı onaylar, bayi ONAYLANAN tutarı öder (kart / havale);
 * izinli bayi "sonra öde" ile verir (süre teslimden başlar; kart, havale veya teslimatta nakit / POS). Ödeme cariye tahsilat yazar; kartla ödenen sipariş iptalinde iyzico'dan iade edilir.
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
        config(['b2b.pay_later_enabled' => true]); // sonra öde canlıda kapalı; kuralları açıkken sınanır
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

    public function test_dealer_chooses_method_and_only_permitted_dealers_see_pay_later(): void
    {
        $this->actingAs($this->rel->dealer->user, 'dealer');
        $this->inTenant($this->business, fn () => app(CartService::class)->add($this->rel, $this->product->id, '2'));
        $this->get(route('dealer.cart'))->assertOk()->assertSee('Ödeme yöntemi')->assertSee('Kredi / banka kartı')->assertSee('Havale / EFT')
            ->assertDontSee('Sonra öde')->assertDontSee('Açık hesap (cari)');

        // İzin verilmeyen ya da artık seçilemeyen yöntem reddedilir; yöntem seçmeden sipariş verilemez.
        foreach (['pay_later', 'open_account', 'on_delivery', null] as $method) {
            $this->post(route('dealer.cart.submit'), array_filter(['address_id' => $this->rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid(), 'payment_method' => $method]))
                ->assertSessionHasErrors('cart');
        }

        $this->inTenant($this->business, fn () => $this->rel->update(['allow_pay_later' => true, 'payment_term_days' => 30]));
        $this->get(route('dealer.cart'))->assertSee('Sonra öde · teslimden 30 gün sonra');
        $this->post(route('dealer.cart.submit'), ['address_id' => $this->rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid(), 'payment_method' => 'pay_later'])
            ->assertRedirect();
        $order = $this->inTenant($this->business, fn () => Order::firstOrFail());
        $this->assertSame(['pay_later', 30], [$order->payment_method, $order->payment_term_days]);

        // Sonra öde: ödeme beklenir ama hazırlanmasını ve yola çıkmasını engellemez.
        $order = $this->approve($order, '2');
        $this->assertSame('awaiting', $order->payment_status);
        $this->post(route('business.orders.prepare', $order))->assertSessionHasNoErrors();
    }

    public function test_pay_later_order_is_delivered_unpaid_and_due_date_starts_at_delivery(): void
    {
        $this->inTenant($this->business, fn () => $this->rel->update(['allow_pay_later' => true, 'payment_term_days' => 15]));
        $order = $this->approve($this->submit('2', 'pay_later'), '2');
        $this->post(route('business.orders.ship', $order))->assertSessionHasNoErrors();
        $this->post(route('business.orders.complete', $order))->assertSessionHasNoErrors();

        $order = $this->inTenant($this->business, fn () => $order->fresh());
        $this->assertSame(['completed', 'awaiting'], [$order->status, $order->payment_status]);
        $this->assertSame(now('Europe/Istanbul')->addDays(15)->toDateString(), $order->payment_due_on->toDateString());

        $this->actingAs($this->rel->dealer->user, 'dealer')->get(route('dealer.orders.show', $order))->assertOk()
            ->assertSee('Son ödeme günü')->assertSee($order->payment_due_on->format('d.m.Y'))->assertSee('Kartla öde')->assertSee('TR28 0004 6098 7654 3210 9876 54');
        $this->get(route('dealer.account.statement'))->assertOk()->assertSeeText('₺24,00 ödemeniz var')->assertSee('15 gün kaldı');

        // Teslimatta nakit alındı: tamamlanmış sonra öde siparişine kaydedilir.
        $this->actingAs($this->business->user, 'business')
            ->post(route('business.orders.payments.store', $order), ['method' => 'cash', 'paid_on' => now('Europe/Istanbul')->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('paid', $this->inTenant($this->business, fn () => $order->fresh()->payment_status));
        $this->actingAs($this->rel->dealer->user, 'dealer')->get(route('dealer.account.statement'))->assertSee('Borcunuz yok');
    }

    public function test_cash_and_pos_are_only_for_pay_later_orders(): void
    {
        $order = $this->approve($this->submit('2', 'transfer'), '2');
        $this->actingAs($this->business->user, 'business')->get(route('business.orders.show', $order))->assertOk()->assertDontSee('Fiziki POS');
        $this->post(route('business.orders.payments.store', $order), ['method' => 'cash', 'paid_on' => now('Europe/Istanbul')->toDateString()])
            ->assertSessionHasErrors('order');
        $this->post(route('business.orders.payments.store', $order), ['method' => 'transfer', 'paid_on' => now('Europe/Istanbul')->toDateString()])
            ->assertSessionHasNoErrors();
    }

    public function test_lump_sum_payment_closes_oldest_orders_and_reopens_on_cancel(): void
    {
        $this->inTenant($this->business, fn () => $this->rel->update(['allow_pay_later' => true, 'payment_term_days' => 30]));
        $first = $this->approve($this->submit('2', 'pay_later'), '2');   // 24,00
        $second = $this->approve($this->submit('5', 'pay_later'), '5');  // 60,00
        $account = $this->inTenant($this->business, fn () => CurrentAccount::where('dealer_relationship_id', $this->rel->id)->sole());

        // Siparişe bağlanmayan 40 ₺: ilk sipariş kapanır, ikincinin 16 ₺'si düşer.
        $this->post(route('business.accounts.collections.store', $account), ['amount' => '40', 'collected_on' => now('Europe/Istanbul')->toDateString(), 'method' => 'transfer'])
            ->assertSessionHasNoErrors();
        $this->inTenant($this->business, function () use ($first, $second) {
            $this->assertSame('paid', $first->fresh()->payment_status);
            $this->assertSame('awaiting', $second->fresh()->payment_status);
            $this->assertSame('44.00', app(OrderPaymentService::class)->dueAmount($second->fresh()));
        });
        $this->get(route('business.accounts.show', $account))->assertOk()->assertSee($second->order_no)->assertSee('₺44,00')->assertSee('Teslim bekleniyor');

        // Kısmen kapanan sipariş kartla ödenemez (kart tutarın tamamını çeker); kalan havale ile ödenir.
        $this->actingAs($this->rel->dealer->user, 'dealer')->post(route('dealer.orders.pay', $second))->assertSessionHasErrors('payment');

        // Toplu ödeme iptal edilirse, kendi ödeme kaydı olmadan kapanan sipariş yeniden ödeme bekler.
        $collection = $this->inTenant($this->business, fn () => CollectionEntry::whereNull('order_id')->sole());
        $this->actingAs($this->business->user, 'business')->post(route('business.collections.cancel', $collection), ['reason' => 'Hatalı'])->assertSessionHasNoErrors();
        $this->assertSame('awaiting', $this->inTenant($this->business, fn () => $first->fresh()->payment_status));
    }

    public function test_pay_later_is_blocked_by_overdue_debt_and_credit_limit(): void
    {
        $this->inTenant($this->business, fn () => $this->rel->update(['allow_pay_later' => true, 'payment_term_days' => 30, 'credit_limit' => '30.00']));
        $this->submit('2', 'pay_later'); // 24,00: limit içinde

        // Onaylanmamış sonra öde siparişi de limite sayılır: 24 + 24 > 30.
        $this->actingAs($this->rel->dealer->user, 'dealer');
        $this->inTenant($this->business, fn () => app(CartService::class)->add($this->rel, $this->product->id, '2'));
        $this->get(route('dealer.cart'))->assertSee('borç limitiniz');
        $this->post(route('dealer.cart.submit'), ['address_id' => $this->rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid(), 'payment_method' => 'pay_later'])
            ->assertSessionHasErrors('cart');
        $this->post(route('dealer.cart.submit'), ['address_id' => $this->rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid(), 'payment_method' => 'transfer'])
            ->assertSessionHasNoErrors();

        // Vadesi geçmiş borç varken sonra öde seçilemez.
        $this->inTenant($this->business, function () {
            $this->rel->update(['credit_limit' => null]);
            app(LedgerService::class)->setOpening(CurrentAccount::where('dealer_relationship_id', $this->rel->id)->sole(), '50.00', now()->subDays(3)->toDateString(), null);
            app(CartService::class)->add($this->rel, $this->product->id, '1');
        });
        $this->get(route('dealer.cart'))->assertSee('Vadesi geçmiş');
        $this->post(route('dealer.cart.submit'), ['address_id' => $this->rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid(), 'payment_method' => 'pay_later'])
            ->assertSessionHasErrors('cart');
    }

    public function test_pay_later_switched_off_in_code_cannot_be_used_or_enabled_from_panel(): void
    {
        config(['b2b.pay_later_enabled' => false]);
        $this->inTenant($this->business, fn () => $this->rel->update(['allow_pay_later' => true, 'payment_term_days' => 30, 'credit_limit' => '500.00']));
        $address = $this->inTenant($this->business, fn () => $this->rel->addresses()->value('id'));

        // Bayi: izin kaydı olsa da sepette görünmez, gönderimde ve yöntem değiştirmede reddedilir.
        $this->actingAs($this->rel->dealer->user, 'dealer');
        $this->inTenant($this->business, fn () => app(CartService::class)->add($this->rel, $this->product->id, '2'));
        $this->get(route('dealer.cart'))->assertOk()->assertSee('Havale / EFT')->assertDontSee('Sonra öde');
        $this->post(route('dealer.cart.submit'), ['address_id' => $address, 'submit_token' => (string) Str::uuid(), 'payment_method' => 'pay_later'])
            ->assertSessionHasErrors('cart');
        $this->assertSame(0, $this->inTenant($this->business, fn () => Order::count()));
        $this->post(route('dealer.cart.submit'), ['address_id' => $address, 'submit_token' => (string) Str::uuid(), 'payment_method' => 'transfer'])->assertRedirect();
        $order = $this->approve($this->inTenant($this->business, fn () => Order::with('items')->firstOrFail()), '2');
        $this->flushSession();
        $this->actingAs($this->rel->dealer->user, 'dealer');
        $this->post(route('dealer.orders.payment-method', $order), ['payment_method' => 'pay_later']);
        $this->assertSame('transfer', $this->inTenant($this->business, fn () => $order->fresh()->payment_method));
        $this->assertNotNull($this->inTenant($this->business, fn () => app(PayLaterPolicy::class)->blockReason($this->rel->fresh(), '1')));

        // Toptancı: anahtar ve vade alanları panelde yok; gönderilen değerler yok sayılır, kayıt değişmez.
        $this->flushSession();
        $this->actingAs($this->business->user, 'business')->get(route('business.dealers.show', $this->rel))->assertOk()
            ->assertDontSee('Sonra ödeyebilir')->assertDontSee('Borç limiti (₺)');
        $this->put(route('business.dealers.update', $this->rel), ['dealer_code' => 'YENI-1', 'payment_term_days' => '0', 'allow_pay_later' => '0', 'credit_limit' => ''])
            ->assertSessionHasNoErrors();
        $rel = $this->inTenant($this->business, fn () => $this->rel->fresh());
        $this->assertSame(['YENI-1', true, 30, '500.00'], [$rel->dealer_code, $rel->allow_pay_later, (int) $rel->payment_term_days, (string) $rel->credit_limit]);
        $this->assertFalse($rel->payLaterEnabled());
    }

    public function test_business_sets_pay_later_terms(): void
    {
        $this->actingAs($this->business->user, 'business')->get(route('business.dealers.show', $this->rel))->assertOk()
            ->assertSee('Sonra ödeyebilir')->assertSee('Borç limiti (₺)')->assertDontSee('Açık hesapla');
        $this->put(route('business.dealers.update', $this->rel), ['dealer_code' => $this->rel->dealer_code, 'payment_term_days' => '0', 'allow_pay_later' => '1', 'credit_limit' => '1.500,50'])
            ->assertSessionHasNoErrors();
        $rel = $this->inTenant($this->business, fn () => $this->rel->fresh());
        $this->assertSame([true, '1500.50', 'Teslimatta öde'], [$rel->allow_pay_later, (string) $rel->credit_limit, $rel->payLaterLabel()]);

        $this->put(route('business.dealers.update', $this->rel), ['dealer_code' => $this->rel->dealer_code, 'payment_term_days' => '30', 'credit_limit' => ''])->assertSessionHasNoErrors();
        $rel = $this->inTenant($this->business, fn () => $this->rel->fresh());
        $this->assertSame([false, null], [$rel->allow_pay_later, $rel->credit_limit]);
        $this->put(route('business.dealers.update', $this->rel), ['dealer_code' => $this->rel->dealer_code, 'payment_term_days' => '30', 'credit_limit' => 'abc'])
            ->assertSessionHasErrors('credit_limit');
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

        $form = [
            'payments_enabled' => '1', 'card_enabled' => '1', 'iyzico_mode' => 'sandbox', 'iyzico_api_key' => 'api-123', 'iyzico_secret_key' => 'sec-456',
            'installments' => ['1', '6', '7'], 'delivery_fee' => '25,50', 'delivery_free_over' => '',
            'transfer_enabled' => '1', 'transfer_accounts' => [['holder' => 'Yapboz', 'iban' => 'TR28 0004 6098 7654 3210 9876 54']],
        ];
        // Anahtarlar kaydedilmeden iyzico'da denenir; kabul edilmezse hiçbir ayar kaydedilmez.
        Http::fakeSequence('*/payment/iyzipos/installment')->push(['status' => 'failure', 'errorMessage' => 'api bilgileri bulunamadı'])->push(['status' => 'success'])->whenEmpty(Http::response(['status' => 'success']));
        $this->put(route('business.payments.update'), $form)->assertSessionHasErrors('iyzico_api_key');
        $this->assertNull(BusinessSetting::where('business_id', $this->business->id)->value('iyzico_api_key'));
        $this->put(route('business.payments.update'), $form)->assertSessionHasNoErrors();

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

    /** Kartla ödenmiş, onaylanmış sipariş (10 × 10,00 + %20 KDV = 120,00). */
    private function paidByCard(array $refund = ['status' => 'success', 'paymentTransactionId' => 'tx-55']): Order
    {
        $order = $this->approve($this->submit('10', 'card'));
        $this->fakeIyzico('120.00', refund: $refund);
        $this->actingAs($this->rel->dealer->user, 'dealer')->post(route('dealer.orders.pay', $order));
        $this->flushSession();
        auth()->forgetGuards();
        $this->post('/bayi/odeme/iyzico/donus', ['token' => 'tok-1']);
        $this->flushSession();

        return $this->inTenant($this->business, fn () => $order->fresh(['items', 'payments']));
    }

    /** Bayi iade talebi açar, toptancı onaylayıp teslim alır. */
    private function returnViaRequest(Order $order, string $qty): TestResponse
    {
        $itemId = $order->items->first()->id;
        $this->flushSession();
        $this->actingAs($this->rel->dealer->user, 'dealer')
            ->post(route('dealer.returns.store', $order), ['qty' => [$itemId => $qty], 'reason' => 'excess'])->assertSessionHasNoErrors();
        $request = $this->inTenant($this->business, fn () => OrderReturnRequest::latest('id')->first());
        $requestItemId = $this->inTenant($this->business, fn () => $request->items()->value('id'));
        $this->flushSession();
        $this->actingAs($this->business->user, 'business')->post(route('business.returns.approve', $request))->assertSessionHasNoErrors();

        return $this->post(route('business.returns.receive', $request), ['received' => [$requestItemId => $qty], 'resellable' => [$requestItemId => $qty]]);
    }

    public function test_dealer_cancels_paid_order_before_shipping_and_card_is_refunded(): void
    {
        $order = $this->paidByCard();
        $this->actingAs($this->business->user, 'business')->post(route('business.orders.prepare', $order))->assertSessionHasNoErrors();

        $this->flushSession();
        $this->actingAs($this->rel->dealer->user, 'dealer');
        $this->get(route('dealer.orders.show', $order))->assertSee('Siparişi iptal et')->assertSee('Kart ödemeniz otomatik olarak iade edilir');
        $this->post(route('dealer.orders.cancel', $order), ['reason' => 'Vazgeçtim'])->assertSessionHasNoErrors()->assertSessionHas('status');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/payment/refund') && $r['price'] === '120.00');
        $order = $this->inTenant($this->business, fn () => $order->fresh('payments'));
        $this->assertSame(['cancelled', 'refunded', 'refunded', '120.00'], [$order->status, $order->payment_status, $order->payments->first()->status, (string) $order->payments->first()->refunded_amount]);
        $this->assertSame('0.00', $this->balance($this->business, $this->rel));
        $this->assertSame('100.000', $this->stockQty($this->business, $this->product));
    }

    public function test_partial_then_full_return_refunds_card_and_closes_order(): void
    {
        $order = $this->paidByCard();
        $this->actingAs($this->business->user, 'business')->post(route('business.orders.ship', $order))->assertSessionHasNoErrors();

        // Yoldaki siparişten 2 adet iade: teslim alınınca sipariş tamamlanır, 24,00 karta geri ödenir.
        $this->returnViaRequest($order, '2')->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/payment/refund') && $r['price'] === '24.00' && $r['paymentTransactionId'] === 'tx-55');
        $order = $this->inTenant($this->business, fn () => $order->fresh(['payments', 'items']));
        $payment = $order->payments->first();
        $this->assertSame(['completed', 'paid', 'succeeded', '24.00'], [$order->status, $order->payment_status, $payment->status, (string) $payment->refunded_amount]);
        $this->assertSame('24.00', (string) $this->inTenant($this->business, fn () => OrderReturn::latest('id')->value('refunded_to_card')));
        $this->assertSame('0.00', $this->balance($this->business, $this->rel)); // borç 120 − tahsilat 120 − iade 24 + karta ödenen 24

        $this->flushSession();
        $this->actingAs($this->rel->dealer->user, 'dealer')->get(route('dealer.orders.show', $order))->assertSee('₺24,00 kartınıza iade edildi', false);

        // Kalan 8 adet: 96,00 karta; ödeme tamamen iade, sipariş iptal edildi.
        $this->returnViaRequest($order, '8')->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/payment/refund') && $r['price'] === '96.00');
        $order = $this->inTenant($this->business, fn () => $order->fresh('payments'));
        $this->assertSame(['cancelled', 'refunded', 'refunded', '120.00'], [$order->status, $order->payment_status, $order->payments->first()->status, (string) $order->payments->first()->refunded_amount]);
        $this->assertSame('0.00', $this->balance($this->business, $this->rel));
        $this->assertSame('100.000', $this->stockQty($this->business, $this->product));
    }

    public function test_card_refund_failure_on_return_keeps_request_open_and_writes_nothing(): void
    {
        $order = $this->paidByCard(['status' => 'failure', 'errorMessage' => 'Banka reddetti']);
        $this->actingAs($this->business->user, 'business')->post(route('business.orders.complete', $order))->assertSessionHasNoErrors();

        $this->returnViaRequest($order, '3')->assertSessionHasErrors('return');
        $this->assertSame('approved', $this->inTenant($this->business, fn () => OrderReturnRequest::latest('id')->value('status')));
        $this->assertSame(0, $this->inTenant($this->business, fn () => OrderReturn::count()));
        $order = $this->inTenant($this->business, fn () => $order->fresh('payments'));
        $this->assertSame(['completed', 'paid', '0.00'], [$order->status, $order->payment_status, (string) $order->payments->first()->refunded_amount]);
        $this->assertSame('0.00', $this->balance($this->business, $this->rel));
        $this->assertSame('90.000', $this->stockQty($this->business, $this->product));
    }
}
