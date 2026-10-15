<?php

namespace Tests\Feature\Ordering;

use App\Domain\Inventory\InsufficientStock;
use App\Domain\Inventory\StockService;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Models\CartItem;
use App\Models\CurrentAccountMovement;
use App\Models\Order;
use App\Models\StockMovement;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** docs/03-is-kurallari.md §3 ve §6 — sipariş, stok ve cari tutarlılığı. */
class OrderLifecycleTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private function service(): OrderService
    {
        return app(OrderService::class);
    }

    private function approve($business, Order $order, array $qty = [], ?int $warehouseId = null): Order
    {
        return $this->inTenant($business, fn () => $this->service()->approve($order, $qty, $warehouseId ?? $this->defaultWarehouse($business)->id, [], $business->user->id));
    }

    private function assertLedgerInvariant(): void
    {
        $mismatch = DB::select('SELECT ca.id FROM current_accounts ca
            LEFT JOIN (SELECT current_account_id, SUM(debit) - SUM(credit) total FROM current_account_movements GROUP BY current_account_id) m
            ON m.current_account_id = ca.id WHERE ca.balance <> COALESCE(m.total, 0)');
        $this->assertSame([], $mismatch, 'Cari bakiye hareket toplamıyla eşleşmiyor.');
    }

    public function test_submit_creates_order_with_snapshots_and_does_not_touch_stock(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00', 'name' => 'Defter A4']);
        $this->stockUp($business, $product, '50');

        $order = $this->placeOrder($business, $rel, [$product->id => '5']);

        $this->assertSame('submitted', $order->status);
        $this->assertMatchesRegularExpression('/^SIP-\d{4}-000001$/', $order->order_no);
        $this->assertSame('50.00', (string) $order->net_total);
        $this->assertSame('10.00', (string) $order->vat_total);   // %20
        $this->assertSame('60.00', (string) $order->grand_total);
        $this->assertSame('Defter A4', $order->items->first()->name);
        $this->assertSame('Moda Cd. 1', $order->ship_address);
        $this->assertSame('50.000', $this->stockQty($business, $product), 'Sipariş oluşturmak stok ayırmamalı.');
        $this->assertSame(0, $this->inTenant($business, fn () => CartItem::count()));
    }

    public function test_same_submit_token_creates_only_one_order(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');

        $this->inTenant($business, function () use ($rel, $product) {
            app(CartService::class)->add($rel, $product->id, '1');
            $addressId = $rel->addresses()->value('id');
            $first = $this->service()->submit($rel, $addressId, null, 'aaaaaaaa-1111-4111-8111-111111111111');
            $second = $this->service()->submit($rel, $addressId, null, 'aaaaaaaa-1111-4111-8111-111111111111');
            $this->assertSame($first->id, $second->id);
            $this->assertSame(1, Order::count());
        });
    }

    public function test_cannot_submit_when_stock_is_insufficient_or_dealer_suspended(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '2');

        try {
            $this->placeOrder($business, $rel, [$product->id => '3']);
            $this->fail('Yetersiz stokla sipariş oluşturuldu.');
        } catch (DomainException) {
        }

        $rel->forceFill(['status' => 'suspended'])->saveQuietly();
        $this->expectException(DomainException::class);
        $this->placeOrder($business, $rel, [$product->id => '1']);
    }

    public function test_full_approval_deducts_stock_and_posts_single_ledger_debit(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '20');
        $order = $this->placeOrder($business, $rel, [$product->id => '5']);

        $order = $this->approve($business, $order);

        $this->assertSame('approved', $order->status);
        $this->assertFalse($order->is_partial);
        $this->assertSame('15.000', $this->stockQty($business, $product));
        $this->assertSame('60.00', $this->balance($business, $rel));
        $this->assertNotNull($order->ledger_applied_at);

        // İkinci onay: hiçbir şey değişmez.
        try {
            $this->approve($business, $order);
            $this->fail('Sipariş iki kez onaylandı.');
        } catch (DomainException) {
        }
        $this->assertSame('15.000', $this->stockQty($business, $product));
        $this->assertSame('60.00', $this->balance($business, $rel));
        $this->assertSame(1, $this->inTenant($business, fn () => StockMovement::where('type', 'sale')->count()));
        $this->assertLedgerInvariant();
    }

    public function test_partial_approval_recalculates_totals_from_order_prices(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $a = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $b = $this->makeProduct($business, ['dealer_price' => '4.00']);
        $this->stockUp($business, $a, '10');
        $this->stockUp($business, $b, '10');
        $order = $this->placeOrder($business, $rel, [$a->id => '4', $b->id => '10']);

        // Onay öncesi fiyat değişir: sipariş eski fiyatla hesaplanmaya devam etmeli.
        $a->update(['dealer_price' => '99.00']);

        $itemB = $order->items->firstWhere('product_id', $b->id);
        $order = $this->approve($business, $order, [$itemB->id => '5']);

        $this->assertTrue($order->is_partial);
        // (4 × 10) + (5 × 4) = 60 net, 12 KDV
        $this->assertSame('60.00', (string) $order->net_total);
        $this->assertSame('72.00', (string) $order->grand_total);
        $this->assertSame('5.000', $this->stockQty($business, $b));
        $this->assertSame('72.00', $this->balance($business, $rel));
    }

    public function test_approval_with_insufficient_stock_changes_nothing(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');
        $order = $this->placeOrder($business, $rel, [$product->id => '8']);

        // Sipariş verildikten sonra stok başka nedenle azaldı.
        $this->inTenant($business, fn () => app(StockService::class)->adjust($product->fresh('unit'), $this->defaultWarehouse($business), 'decrease', '5'));

        try {
            $this->approve($business, $order);
            $this->fail('Yetersiz stokla onay verildi.');
        } catch (InsufficientStock) {
        }

        $this->assertSame('submitted', $order->fresh()->status);
        $this->assertSame('5.000', $this->stockQty($business, $product));
        $this->assertSame('0.00', $this->balance($business, $rel));
        $this->assertNull($order->fresh()->stock_applied_at);
    }

    public function test_reject_does_not_touch_stock_or_ledger(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');
        $order = $this->placeOrder($business, $rel, [$product->id => '2']);

        $this->inTenant($business, fn () => $this->service()->reject($order, 'Bölge dışı'));

        $this->assertSame('rejected', $order->fresh()->status);
        $this->assertSame('10.000', $this->stockQty($business, $product));
        $this->expectException(DomainException::class);
        $this->approve($business, $order->fresh());
    }

    public function test_cancel_after_approval_reverses_stock_and_ledger_exactly_once(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');
        $order = $this->approve($business, $this->placeOrder($business, $rel, [$product->id => '3']));

        $this->inTenant($business, fn () => $this->service()->cancelByBusiness($order, 'Müşteri vazgeçti'));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('10.000', $this->stockQty($business, $product));
        $this->assertSame('0.00', $this->balance($business, $rel));
        $this->assertSame(1, $this->inTenant($business, fn () => CurrentAccountMovement::where('type', 'reversal')->count()));

        try {
            $this->inTenant($business, fn () => $this->service()->cancelByBusiness($order, 'Tekrar'));
            $this->fail('Sipariş iki kez iptal edildi.');
        } catch (DomainException) {
        }
        $this->assertSame('10.000', $this->stockQty($business, $product));
        $this->assertSame(1, $this->inTenant($business, fn () => StockMovement::where('type', 'sale_reversal')->count()));
        $this->assertLedgerInvariant();
    }

    public function test_dealer_cancel_request_flow(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');

        // Onay öncesi: doğrudan iptal, etkisiz.
        $pending = $this->placeOrder($business, $rel, [$product->id => '1']);
        $this->inTenant($business, fn () => $this->service()->cancelByDealer($pending, 'Yanlış ürün'));
        $this->assertSame('cancelled', $pending->fresh()->status);

        // Onay sonrası: iptal talebi → reddedilirse önceki duruma döner, kabul edilirse ters kayıt.
        $order = $this->approve($business, $this->placeOrder($business, $rel, [$product->id => '4']));
        $this->inTenant($business, fn () => $this->service()->markPreparing($order));
        $this->inTenant($business, fn () => $this->service()->cancelByDealer($order, 'Erken geldi'));
        $this->assertSame('cancel_requested', $order->fresh()->status);

        $this->inTenant($business, fn () => $this->service()->resolveCancelRequest($order, false, 'Yola çıktı'));
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertSame('6.000', $this->stockQty($business, $product));

        $this->inTenant($business, fn () => $this->service()->cancelByDealer($order, 'Yine de iptal'));
        $this->inTenant($business, fn () => $this->service()->resolveCancelRequest($order, true));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('10.000', $this->stockQty($business, $product));
        $this->assertSame('0.00', $this->balance($business, $rel));
    }

    public function test_ledger_can_be_posted_on_completion_instead_of_approval(): void
    {
        $business = $this->makeBusiness();
        $business->settings()->update(['ledger_post_on' => 'completion']);
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');

        $order = $this->approve($business, $this->placeOrder($business, $rel, [$product->id => '1']));
        $this->assertSame('0.00', $this->balance($business, $rel));
        $this->assertNull($order->ledger_applied_at);

        $this->inTenant($business, fn () => $this->service()->complete($order));
        $this->assertSame('12.00', $this->balance($business, $rel));
        $this->assertLedgerInvariant();
    }

    public function test_completed_order_cannot_be_cancelled(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');
        $order = $this->approve($business, $this->placeOrder($business, $rel, [$product->id => '1']));
        $this->inTenant($business, fn () => $this->service()->complete($order));

        $this->expectException(DomainException::class);
        $this->inTenant($business, fn () => $this->service()->cancelByBusiness($order, 'Geç'));
    }

    public function test_later_address_change_does_not_alter_order(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');
        $order = $this->placeOrder($business, $rel, [$product->id => '1']);

        $this->inTenant($business, fn () => $rel->addresses()->first()->update(['address_line' => 'Yeni adres']));

        $this->assertSame('Moda Cd. 1', $order->fresh()->ship_address);
    }

    public function test_order_numbers_are_sequential_per_business(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $relA = $this->orderingDealer($a);
        $relB = $this->orderingDealer($b);
        $pa = $this->makeProduct($a);
        $pb = $this->makeProduct($b);
        $this->stockUp($a, $pa, '10');
        $this->stockUp($b, $pb, '10');

        $a1 = $this->placeOrder($a, $relA, [$pa->id => '1']);
        $a2 = $this->placeOrder($a, $relA, [$pa->id => '1']);
        $b1 = $this->placeOrder($b, $relB, [$pb->id => '1']);

        $this->assertStringEndsWith('000001', $a1->order_no);
        $this->assertStringEndsWith('000002', $a2->order_no);
        $this->assertStringEndsWith('000001', $b1->order_no);
    }
}
