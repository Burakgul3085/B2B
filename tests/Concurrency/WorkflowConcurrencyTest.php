<?php

namespace Tests\Concurrency;

use App\Domain\Ledger\LedgerService;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Models\CurrentAccountMovement;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * Aşama 9 — iş akışlarının gerçek ayrı PHP süreçleriyle eşzamanlı çalıştırılması.
 * Her senaryoda hangi işlemin önce kazandığından bağımsız olarak veri tutarlı kalmalı:
 * stok = hareketlerin son bakiyesi, cari bakiye = hareket toplamı, çift kayıt yok.
 */
class WorkflowConcurrencyTest extends TestCase
{
    use CreatesOrders, DatabaseTruncation;

    public function test_approve_and_business_cancel_race_leaves_consistent_state(): void
    {
        foreach (['cancel_business', 'cancel_dealer'] as $canceller) {
            $business = $this->makeBusiness();
            $rel = $this->orderingDealer($business);
            $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
            $this->stockUp($business, $product, '10');
            $order = $this->placeOrder($business, $rel, [$product->id => '4']);
            $warehouseId = $this->defaultWarehouse($business)->id;

            $results = $this->runParallel($business->id, [
                ['approve', $order->id, $warehouseId, $business->user->id],
                [$canceller, $order->id],
            ]);
            $this->assertNoErrors($results);

            $order = $this->inTenant($business, fn () => $order->fresh());
            $stock = $this->stockQty($business, $product);
            $balance = $this->balance($business, $rel);
            match ($order->status) {
                // İptal önce geldi (onay reddedildi) ya da onaydan sonra ters kayıtla iptal edildi.
                'cancelled' => [$this->assertSame('10.000', $stock), $this->assertSame('0.00', $balance)],
                // Onay önce geldi; bayi iptali talebe dönüştü.
                'cancel_requested', 'approved' => [$this->assertSame('6.000', $stock), $this->assertSame('48.00', $balance)],
            };
            $this->assertConsistent($business, $order);
        }
    }

    public function test_double_submit_with_same_token_creates_one_order(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');
        $this->inTenant($business, fn () => app(CartService::class)->add($rel, $product->id, '2'));
        $addressId = $this->inTenant($business, fn () => $rel->addresses()->value('id'));
        $token = (string) Str::uuid();

        $results = $this->runParallel($business->id, [
            ['submit', $rel->id, $addressId, $token],
            ['submit', $rel->id, $addressId, $token],
            ['submit', $rel->id, $addressId, $token],
        ]);

        $this->assertSame(['OK', 'OK', 'OK'], $results, implode(' | ', $results));
        $this->inTenant($business, function () {
            $this->assertSame(1, Order::count());
            $this->assertSame(1, DB::table('order_items')->count());
        });
    }

    public function test_collection_cancelled_twice_in_parallel_is_reversed_once(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $account = $this->inTenant($business, fn () => CurrentAccount::where('dealer_relationship_id', $rel->id)->sole());
        $collection = $this->inTenant($business, fn () => app(LedgerService::class)->recordCollection($account, [
            'collected_on' => now()->toDateString(), 'amount' => '75.00', 'method' => 'cash',
        ], $business->user->id));

        $results = $this->runParallel($business->id, [['cancel_collection', $collection->id], ['cancel_collection', $collection->id]]);

        $this->assertNoErrors($results);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'OK')), implode(' | ', $results));
        $this->assertSame('0.00', $this->balance($business, $rel));
        $this->inTenant($business, fn () => $this->assertSame(1, CurrentAccountMovement::where('type', 'reversal')->count()));
        $this->assertNotNull(CollectionEntry::withoutGlobalScopes()->find($collection->id)->cancelled_at);
    }

    public function test_opening_balance_set_in_parallel_only_once(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $accountId = $this->inTenant($business, fn () => CurrentAccount::where('dealer_relationship_id', $rel->id)->value('id'));

        $results = $this->runParallel($business->id, [['opening', $accountId, '100.00'], ['opening', $accountId, '250.00']]);

        $this->assertNoErrors($results);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'OK')), implode(' | ', $results));
        $this->assertContains($this->balance($business, $rel), ['100.00', '250.00']);
        $this->inTenant($business, fn () => $this->assertSame(1, CurrentAccountMovement::where('type', 'opening')->count()));
    }

    public function test_parallel_returns_cannot_exceed_returnable_quantity(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');
        $order = $this->placeOrder($business, $rel, [$product->id => '5']);
        $order = $this->inTenant($business, fn () => app(OrderService::class)->complete(
            app(OrderService::class)->approve($order, [], $this->defaultWarehouse($business)->id, [], $business->user->id)
        ));
        $itemId = $this->inTenant($business, fn () => $order->items()->value('id'));

        $results = $this->runParallel($business->id, [
            ['return', $order->id, $itemId, '3', $business->user->id],
            ['return', $order->id, $itemId, '3', $business->user->id],
        ]);

        $this->assertNoErrors($results);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'OK')), implode(' | ', $results));
        $this->assertSame('8.000', $this->stockQty($business, $product)); // 10 − 5 + 3
        $this->assertSame('24.00', $this->balance($business, $rel));      // 60 − 36
        $this->inTenant($business, fn () => $this->assertSame(1, OrderReturn::count()));
        $this->assertConsistent($business, $order);
    }

    private function assertNoErrors(array $results): void
    {
        foreach ($results as $r) {
            $this->assertStringStartsNotWith('ERROR', $r, implode(' | ', $results));
        }
    }

    /** Stok ve cari değişmezleri: bakiye = hareketler, depo stoğu = son hareket bakiyesi, çift kayıt yok. */
    private function assertConsistent($business, Order $order): void
    {
        $this->inTenant($business, function () use ($order) {
            $this->assertSame([], DB::select('SELECT ca.id FROM current_accounts ca LEFT JOIN (SELECT current_account_id, SUM(debit) - SUM(credit) t FROM current_account_movements GROUP BY current_account_id) m
                ON m.current_account_id = ca.id WHERE ca.balance <> COALESCE(m.t, 0)'));
            $this->assertSame([], DB::select('SELECT ws.id FROM warehouse_stock ws WHERE ws.quantity <> (SELECT sm.balance_after FROM stock_movements sm
                WHERE sm.warehouse_id = ws.warehouse_id AND sm.product_id = ws.product_id ORDER BY sm.id DESC LIMIT 1)'));
            $this->assertLessThanOrEqual(1, StockMovement::where('reference_id', $order->id)->where('type', 'sale')->count());
            $this->assertLessThanOrEqual(1, CurrentAccountMovement::where('order_id', $order->id)->where('type', 'order_debit')->count());
        });
    }

    private function runParallel(int $businessId, array $jobs): array
    {
        $startAt = sprintf('%.6F', microtime(true) + 3);
        $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'b2b_test', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array']);
        $processes = [];
        foreach ($jobs as $args) {
            $action = array_shift($args);
            $cmd = [PHP_BINARY, __DIR__.'/action_worker.php', $action, (string) $businessId, $startAt, ...array_map('strval', $args)];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            $processes[] = [$proc, $pipes];
        }

        $results = [];
        foreach ($processes as [$proc, $pipes]) {
            $out = trim(stream_get_contents($pipes[1]));
            $err = trim(stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            $results[] = $out !== '' ? $out : 'ERROR:'.$err;
        }

        return $results;
    }
}
