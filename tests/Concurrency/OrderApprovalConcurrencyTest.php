<?php

namespace Tests\Concurrency;

use App\Models\CurrentAccountMovement;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * docs/03-is-kurallari.md §10: gerçek ayrı PHP süreçleriyle eşzamanlı sipariş onayı.
 */
class OrderApprovalConcurrencyTest extends TestCase
{
    use CreatesOrders, DatabaseTruncation;

    public function test_two_orders_approved_in_parallel_cannot_oversell(): void
    {
        $business = $this->makeBusiness();
        $relA = $this->orderingDealer($business);
        $relB = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');

        $orderA = $this->placeOrder($business, $relA, [$product->id => '7']);
        $orderB = $this->placeOrder($business, $relB, [$product->id => '7']);
        $warehouseId = $this->defaultWarehouse($business)->id;

        $results = $this->runParallel([
            [$business->id, $orderA->id, $warehouseId, $business->user->id],
            [$business->id, $orderB->id, $warehouseId, $business->user->id],
        ]);
        sort($results);

        $this->assertSame(['INSUFFICIENT', 'OK'], $results, 'Çıktılar: '.implode(' | ', $results));
        $this->assertSame('3.000', $this->stockQty($business, $product));
        $this->inTenant($business, function () {
            $this->assertSame(1, Order::where('status', 'approved')->count());
            $this->assertSame(1, StockMovement::where('type', 'sale')->count());
            $this->assertSame(1, CurrentAccountMovement::where('type', 'order_debit')->count());
        });
    }

    public function test_same_order_approved_twice_in_parallel_is_applied_once(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');
        $order = $this->placeOrder($business, $rel, [$product->id => '4']);
        $warehouseId = $this->defaultWarehouse($business)->id;

        $results = $this->runParallel([
            [$business->id, $order->id, $warehouseId, $business->user->id],
            [$business->id, $order->id, $warehouseId, $business->user->id],
        ]);

        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'OK')), 'Çıktılar: '.implode(' | ', $results));
        $this->assertSame('6.000', $this->stockQty($business, $product));
        $this->assertSame('48.00', $this->balance($business, $rel));
    }

    private function runParallel(array $jobs): array
    {
        $startAt = sprintf('%.6F', microtime(true) + 3);
        $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'b2b_test', 'CACHE_STORE' => 'array']);
        $processes = [];

        foreach ($jobs as $args) {
            $cmd = array_merge([PHP_BINARY, __DIR__.'/approve_worker.php'], array_map('strval', $args), [$startAt]);
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
