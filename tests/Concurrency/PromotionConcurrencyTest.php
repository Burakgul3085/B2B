<?php

namespace Tests\Concurrency;

use App\Domain\Ordering\CartService;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** B2B kupon: tek kullanımlık kupon, ayrı süreçlerden aynı anda gönderilen iki siparişte yalnızca bir kez kullanılır. */
class PromotionConcurrencyTest extends TestCase
{
    use CreatesOrders, DatabaseTruncation;

    public function test_single_use_coupon_cannot_be_used_twice_in_parallel(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '100');
        $this->inTenant($business, fn () => Promotion::create(['name' => 'Tek', 'code' => 'TEK', 'type' => 'percent', 'value' => '10', 'usage_limit' => 1]));

        $jobs = [];
        foreach ([1, 2] as $_) {
            $rel = $this->orderingDealer($business);
            $this->inTenant($business, function () use ($rel, $product) {
                app(CartService::class)->add($rel, $product->id, '2');
                app(CartService::class)->setPromotionCode($rel, 'TEK');
            });
            $jobs[] = ['submit', $rel->id, $this->inTenant($business, fn () => $rel->addresses()->value('id')), (string) Str::uuid()];
        }

        $results = $this->runParallel($business->id, $jobs);
        foreach ($results as $r) {
            $this->assertStringStartsNotWith('ERROR', $r, implode(' | ', $results));
        }

        $this->inTenant($business, function () use ($results) {
            $this->assertSame(1, PromotionRedemption::count(), implode(' | ', $results));
            $this->assertSame(1, Order::where('promotion_code', 'TEK')->count());
            // Kazanamayan sipariş oluşmaz; bayi sepetinde nedeni görür.
            $this->assertSame(1, Order::count());
            $this->assertContains('OK', $results);
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
