<?php

namespace Tests\Feature\Ordering;

use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\CollectionEntry;
use App\Models\DealerRelationship;
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
 * B2B kart ödemesi güvenliği: aynı siparişe ikinci form, geç / ikinci ödemenin otomatik iadesi, ödenmiş siparişte yeni
 * form açılmaması, bayi ekranının canlı kontrolü.
 */
class DealerPaymentSafetyTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private Business $business;

    private DealerRelationship $rel;

    private Product $product;

    private int $seq = 0;

    /** @var array<string, bool> iyzico'da ödenmiş belirteçler */
    private array $paid = [];

    private bool $refundOk = true;

    /** @var list<string> iade edilen işlem kimlikleri */
    private array $refunds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = $this->makeBusiness();
        $this->rel = $this->orderingDealer($this->business);
        $this->product = $this->makeProduct($this->business, ['dealer_price' => '10.00', 'name' => 'Defter A4']);
        $this->stockUp($this->business, $this->product, '100');
        BusinessSetting::where('business_id', $this->business->id)->update([
            'payments_enabled' => true, 'card_enabled' => true, 'iyzico_mode' => 'sandbox', 'installments' => '1',
            'iyzico_api_key' => Crypt::encryptString('sandbox-api'), 'iyzico_secret_key' => Crypt::encryptString('sandbox-secret'),
        ]);
        Http::fake(['*' => fn (HttpRequest $r) => $this->iyzico($r)]);
    }

    private function iyzico(HttpRequest $r)
    {
        if (str_contains($r->url(), 'initialize')) {
            $this->seq++;

            return Http::response(['status' => 'success', 'token' => "tok-{$this->seq}", 'paymentPageUrl' => "https://sandbox.iyzico.test/odeme/{$this->seq}"]);
        }
        if (str_contains($r->url(), 'detail')) {
            if (! ($this->paid[$r['token']] ?? false)) {
                return Http::response(['status' => 'success', 'paymentStatus' => 'INIT_THREEDS', 'basketId' => $r['conversationId']]);
            }

            return Http::response(['status' => 'success', 'paymentStatus' => 'SUCCESS', 'basketId' => $r['conversationId'], 'conversationId' => $r['conversationId'],
                'price' => '120.00', 'paidPrice' => '120.00', 'currency' => 'TRY', 'token' => $r['token'], 'paymentId' => 'p-'.$r['token'], 'fraudStatus' => 1,
                'itemTransactions' => [['itemId' => 'siparis', 'paymentTransactionId' => 'tx-'.$r['token'], 'paidPrice' => '120.00']]]);
        }
        if (str_contains($r->url(), '/payment/refund')) {
            $this->refunds[] = $r['paymentTransactionId'];

            return Http::response($this->refundOk ? ['status' => 'success'] : ['status' => 'failure', 'errorMessage' => 'Banka reddetti']);
        }

        return Http::response([], 404);
    }

    private function approvedOrder(): Order
    {
        $order = $this->inTenant($this->business, function () {
            app(CartService::class)->add($this->rel, $this->product->id, '10');

            return app(OrderService::class)->submit($this->rel, $this->rel->addresses()->value('id'), null, (string) Str::uuid(), 'card')->fresh('items');
        });
        $this->actingAs($this->business->user, 'business')
            ->post(route('business.orders.approve', $order), ['warehouse_id' => $this->defaultWarehouse($this->business)->id, 'qty' => [$order->items->first()->id => '10']])
            ->assertSessionHasNoErrors();

        return $order;
    }

    private function payments(Order $order): array
    {
        return $this->inTenant($this->business, fn () => OrderPayment::where('order_id', $order->id)->orderBy('id')->pluck('status', 'token')->all());
    }

    public function test_second_form_closes_first_and_late_payment_of_first_is_refunded(): void
    {
        $order = $this->approvedOrder();
        $dealer = $this->actingAs($this->rel->dealer->user, 'dealer');
        $dealer->post(route('dealer.orders.pay', $order))->assertRedirect('https://sandbox.iyzico.test/odeme/1');
        $dealer->post(route('dealer.orders.pay', $order))->assertRedirect('https://sandbox.iyzico.test/odeme/2');
        $this->assertSame(['tok-1' => 'expired', 'tok-2' => 'initialized'], $this->payments($order));

        $this->paid['tok-2'] = true;
        $this->post('/bayi/odeme/iyzico/donus', ['token' => 'tok-2']);
        $this->paid['tok-1'] = true; // eski sekmedeki form da ödendi
        $this->post('/bayi/odeme/iyzico/donus', ['token' => 'tok-1']);

        $this->assertSame(['tok-1' => 'auto_refunded', 'tok-2' => 'succeeded'], $this->payments($order));
        $this->assertSame(['tx-tok-1'], $this->refunds);
        $this->assertSame(1, $this->inTenant($this->business, fn () => CollectionEntry::count()), 'cariye tek tahsilat');
        $this->assertSame('0.00', $this->balance($this->business, $this->rel));
        $this->actingAs($this->business->user, 'business')->get(route('business.orders.show', $order))->assertSee('Fazla ödeme, iade edildi')->assertSee('TEST');
    }

    public function test_paid_but_unreturned_payment_blocks_a_new_form_and_failed_auto_refund_is_flagged(): void
    {
        $order = $this->approvedOrder();
        $dealer = $this->actingAs($this->rel->dealer->user, 'dealer');
        $dealer->post(route('dealer.orders.pay', $order));
        $this->paid['tok-1'] = true; // ödendi ama dönüş gelmedi

        $dealer->from(route('dealer.orders.show', $order))->post(route('dealer.orders.pay', $order))
            ->assertSessionHasErrors(['payment' => 'Bu siparişin ödemesi zaten alındı; yeniden ödeme yapmayın.']);
        $this->assertSame(1, $this->seq, 'ikinci form açılmadı');
        $this->assertSame('paid', $this->inTenant($this->business, fn () => $order->fresh()->payment_status));

        // Süresi dolmuş bir deneme sonradan ödenir, iade reddedilir: işaretlenir.
        $this->inTenant($this->business, fn () => OrderPayment::create(['order_id' => $order->id, 'driver' => 'iyzico', 'status' => 'expired', 'amount' => '120.00', 'token' => 'tok-old']));
        $this->refundOk = false;
        $this->paid['tok-old'] = true;
        $this->post('/bayi/odeme/iyzico/donus', ['token' => 'tok-old']);
        $this->assertSame('refund_failed', $this->payments($order)['tok-old']);
        $this->assertSame(1, $this->inTenant($this->business, fn () => CollectionEntry::count()));
    }

    public function test_dealer_order_page_checks_open_payment_live(): void
    {
        $order = $this->approvedOrder();
        $dealer = $this->actingAs($this->rel->dealer->user, 'dealer');
        $dealer->post(route('dealer.orders.pay', $order));
        $dealer->get(route('dealer.orders.show', $order))->assertSee('Açık bir kart ödemeniz var')->assertSee('paymentWatcher', false);

        $this->travel(15)->seconds();
        $dealer->getJson(route('dealer.orders.payment-status', $order))->assertOk()->assertJson(['status' => 'awaiting']);
        $this->paid['tok-1'] = true;
        $this->travel(15)->seconds();
        $dealer->getJson(route('dealer.orders.payment-status', $order))->assertOk()->assertJson(['status' => 'paid']);
    }
}
