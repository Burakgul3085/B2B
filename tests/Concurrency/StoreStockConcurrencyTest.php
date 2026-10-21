<?php

namespace Tests\Concurrency;

use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreStockHold;
use App\Models\Store\StoreStockMovement;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Mağaza stoğunda gerçek eşzamanlılık: iki ayrı PHP süreci aynı son ürünleri aynı anda ayırmaya veya satmaya çalışır.
 * Veriler commit edilmiş olmalı (süreçler test transaction'ını göremez), bu yüzden truncation kullanılır.
 */
class StoreStockConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private function variantWithStock(int $qty): StoreProductVariant
    {
        $product = StoreProduct::create(['name' => 'Son Ürün', 'slug' => 'son-urun-'.uniqid()]);
        $variant = $product->variants()->create(['sku' => 'SON-'.uniqid(), 'price' => '10', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($variant, $qty);

        return $variant;
    }

    public function test_two_parallel_checkouts_cannot_hold_the_same_last_items(): void
    {
        $variant = $this->variantWithStock(10);

        $results = $this->runParallel([['hold', $variant->id, 7, 'checkout:a'], ['hold', $variant->id, 7, 'checkout:b']]);

        sort($results);
        $this->assertSame(['INSUFFICIENT', 'OK'], $results, 'Çıktılar: '.implode(' | ', $results));
        $this->assertSame(1, StoreStockHold::count());
        $this->assertSame(10, $variant->fresh()->stock_quantity, 'ayırma stoğu düşmez');
    }

    public function test_two_parallel_sales_cannot_oversell(): void
    {
        $variant = $this->variantWithStock(10);

        $results = $this->runParallel([['sell', $variant->id, 7, 'order:a'], ['sell', $variant->id, 7, 'order:b']]);

        sort($results);
        $this->assertSame(['INSUFFICIENT', 'OK'], $results, 'Çıktılar: '.implode(' | ', $results));
        $this->assertSame(3, $variant->fresh()->stock_quantity);
        $this->assertSame(1, StoreStockMovement::where('type', 'sale')->count());
    }

    /** @return string[] her sürecin çıktısı */
    private function runParallel(array $jobs): array
    {
        $startAt = sprintf('%.6F', microtime(true) + 2.5); // süreçlerin açılması için pay
        $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'b2b_test', 'CACHE_STORE' => 'array']);
        $processes = [];

        foreach ($jobs as $args) {
            $cmd = array_merge([PHP_BINARY, __DIR__.'/store_stock_worker.php'], array_map('strval', $args), [$startAt, '600']);
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
