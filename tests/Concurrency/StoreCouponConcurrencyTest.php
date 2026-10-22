<?php

namespace Tests\Concurrency;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreDiscountRedemption;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kupon kullanım sınırında gerçek eşzamanlılık: iki ayrı PHP süreci, tek kullanımlık kuponla aynı anda sipariş verir.
 * İndirim satırı kilitlendiği için yalnızca biri kullanabilir; diğerinin siparişi oluşmaz.
 */
class StoreCouponConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** Migration'la yüklenen veriler boşaltılmaz. */
    protected $exceptTables = ['migrations', 'tr_provinces', 'tr_districts', 'store_legal_documents'];

    public function test_single_use_coupon_cannot_be_redeemed_twice_in_parallel(): void
    {
        $this->ensureSeedData();
        StoreSetting::put(['is_open' => '1', 'cod_enabled' => '1', 'cod_max' => '5000.00', 'cod_max_open' => '10', 'cod_first_order_approval' => '0']);
        $variant = StoreProduct::create(['name' => 'Kalem', 'slug' => 'kalem-'.uniqid(), 'is_published' => true])
            ->variants()->create(['sku' => 'K-'.uniqid(), 'price' => '100', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($variant, 20);
        StoreDiscount::create(['name' => 'Tek', 'code' => 'TEK', 'type' => 'fixed', 'value' => '10', 'usage_limit' => 1]);

        $carts = [];
        foreach ([1, 2] as $i) {
            $cart = app(CartService::class)->createGuest();
            app(CartService::class)->add($cart, $variant->id, 1);
            $cart->update(['coupon_code' => 'TEK']);
            $carts[] = $cart->id;
        }

        $results = $this->runParallel([[$carts[0], '05320000001', 'a@ornek.com'], [$carts[1], '05320000002', 'b@ornek.com']]);

        sort($results);
        $this->assertSame(['OK', 'REFUSED'], $results, 'Çıktılar: '.implode(' | ', $results));
        $this->assertSame(1, StoreDiscountRedemption::where('status', 'active')->count());
        $this->assertSame(1, StoreOrder::count());
        $this->assertSame(19, $variant->fresh()->stock_quantity, 'reddedilen siparişin stoğu düşmedi');
    }

    /**
     * Paketteki diğer eşzamanlılık testleri tüm tabloları boşaltır; migration'la yüklenen il/ilçe verisi ve
     * yasal metinlerin ilk sürümleri gerekirse yeniden yüklenir.
     */
    private function ensureSeedData(): void
    {
        if (! DB::table('tr_districts')->exists()) {
            $data = json_decode(file_get_contents(database_path('data/turkiye-il-ilce.json')), true, flags: JSON_THROW_ON_ERROR);
            foreach ($data as $plate => $province) {
                DB::table('tr_provinces')->insert(['id' => (int) $plate, 'name' => $province['name']]);
                DB::table('tr_districts')->insert(array_map(fn ($name) => ['province_id' => (int) $plate, 'name' => $name], $province['districts']));
            }
        }
        if (! DB::table('store_legal_documents')->exists()) {
            $templates = (require database_path('data/store-legal-templates.php')) + (require database_path('data/store-legal-templates-m9.php'));
            foreach ($templates as $key => [$title, $body]) {
                DB::table('store_legal_documents')->insert(['key' => $key, 'version' => 1, 'title' => $title, 'body' => $body, 'created_at' => now()]);
            }
        }
    }

    /** @return string[] her sürecin çıktısı */
    private function runParallel(array $jobs): array
    {
        $startAt = sprintf('%.6F', microtime(true) + 3.0); // süreçlerin açılması ve taslak hazırlığı için pay
        $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'b2b_test', 'CACHE_STORE' => 'array']);
        $processes = [];
        foreach ($jobs as $args) {
            $cmd = array_merge([PHP_BINARY, __DIR__.'/store_coupon_worker.php'], array_map('strval', $args), [$startAt, '600']);
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
