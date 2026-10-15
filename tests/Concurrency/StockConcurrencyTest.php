<?php

namespace Tests\Concurrency;

use App\Domain\Inventory\StockService;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/**
 * Gerçek eşzamanlılık: iki ayrı PHP süreci aynı stok satırından aynı anda düşmeye çalışır.
 * Veriler commit edilmiş olmalı (süreçler test transaction'ını göremez), bu yüzden truncation kullanılır.
 */
class StockConcurrencyTest extends TestCase
{
    use CreatesCatalog, DatabaseTruncation;

    public function test_two_parallel_sales_cannot_oversell(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);
        $this->inTenant($business, fn () => app(StockService::class)->adjust($product, $warehouse, 'increase', '10'));

        // Stok 10; iki süreç 7'şer adet düşmeye çalışır. Yalnızca biri başarılı olmalı.
        $results = $this->runParallel([
            [$business->id, $warehouse->id, $product->id, '7', 'concurrency:a'],
            [$business->id, $warehouse->id, $product->id, '7', 'concurrency:b'],
        ]);

        sort($results);
        $this->assertSame(['INSUFFICIENT', 'OK'], $results, 'Çıktılar: '.implode(' | ', $results));

        $this->inTenant($business, function () use ($product, $warehouse) {
            $this->assertSame('3.000', (string) WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('quantity'));
            $this->assertSame(1, StockMovement::where('type', 'sale')->count());
        });
    }

    public function test_same_operation_sent_twice_in_parallel_applies_once(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);
        $this->inTenant($business, fn () => app(StockService::class)->adjust($product, $warehouse, 'increase', '10'));

        $results = $this->runParallel([
            [$business->id, $warehouse->id, $product->id, '2', 'order:42:sale:1'],
            [$business->id, $warehouse->id, $product->id, '2', 'order:42:sale:1'],
        ]);

        $this->assertContains('OK', $results);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'OK')), 'Çıktılar: '.implode(' | ', $results));

        $this->inTenant($business, function () use ($product, $warehouse) {
            $this->assertSame('8.000', (string) WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('quantity'));
        });
    }

    /** @return string[] her sürecin çıktısı */
    private function runParallel(array $jobs): array
    {
        $startAt = sprintf('%.6F', microtime(true) + 2.5); // süreçlerin açılması için pay
        $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'b2b_test', 'CACHE_STORE' => 'array']);
        $processes = [];

        foreach ($jobs as $args) {
            $cmd = array_merge([PHP_BINARY, __DIR__.'/stock_worker.php'], array_map('strval', $args), [$startAt, '600']);
            $pipes = [];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            $this->assertIsResource($proc);
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
