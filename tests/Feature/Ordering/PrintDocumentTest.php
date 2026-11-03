<?php

namespace Tests\Feature\Ordering;

use App\Domain\Ordering\OrderService;
use App\Models\CurrentAccount;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** B2B yazdırma belgeleri (components/print-document): sipariş ve cari ekstre, toptancı ve bayi tarafı. */
class PrintDocumentTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private function approvedOrder(): array
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00', 'name' => 'Kurşun kalem']);
        $this->stockUp($business, $product, '20');
        $order = $this->placeOrder($business, $rel, [$product->id => '5']);
        $this->inTenant($business, fn () => app(OrderService::class)->approve($order->fresh('items'), [], $this->defaultWarehouse($business)->id, [], $business->user->id));

        return [$business, $rel, Order::withoutGlobalScopes()->find($order->id)];
    }

    private function assertPrintDocument(TestResponse $response, string $title, array $see): void
    {
        $response->assertOk()->assertSee('print-document', false)->assertSee($title);
        foreach ($see as $text) {
            $response->assertSee($text);
        }
    }

    public function test_order_page_prints_document_with_business_and_dealer_on_both_sides(): void
    {
        [$business, $rel, $order] = $this->approvedOrder();

        $this->actingAs($business->user, 'business');
        $this->assertPrintDocument($this->get(route('business.orders.show', $order)), 'Sipariş',
            [$business->display_name, $rel->dealer->legal_name, $order->order_no, 'Kurşun kalem', 'Teslimat adresi']);
        $this->get(route('business.orders.show', $order))->assertSee('data-print', false);

        $this->app['auth']->forgetGuards();
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->assertPrintDocument($this->get(route('dealer.orders.show', $order)), 'Sipariş',
            [$business->display_name, $rel->dealer->legal_name, $order->order_no, 'Kurşun kalem', 'Son ödeme günü']);
    }

    public function test_statement_pages_print_document_with_period_and_balance(): void
    {
        [$business, $rel] = $this->approvedOrder();
        $account = $this->inTenant($business, fn () => CurrentAccount::where('dealer_relationship_id', $rel->id)->first());

        $this->actingAs($business->user, 'business');
        $this->assertPrintDocument($this->get(route('business.accounts.show', $account)), 'Cari Hesap Ekstresi',
            [$business->display_name, $rel->dealer->legal_name, 'Cari kodu '.$account->code, 'Dönem', 'Güncel bakiye']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->assertPrintDocument($this->get(route('dealer.account.statement')), 'Cari Hesap Ekstresi',
            [$business->display_name, $rel->dealer->legal_name, 'Cari kodu '.$account->code, 'Güncel bakiye']);
    }
}
