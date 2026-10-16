<?php

namespace Tests\Performance;

use App\Domain\Tenancy\TenantContext;
use App\Models\CurrentAccount;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use App\Models\Order;
use Database\Seeders\VolumeSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Aşama 9 — büyük veriyle sayfa süreleri ve sorgu sayıları.
 * Çalıştırma: php artisan test --group=performance   (normal test koşusuna dahil değildir)
 * Sonuçlar: storage/app/performans/sonuclar.json ve .md
 */
#[Group('performance')]
class PagePerformanceTest extends TestCase
{
    use DatabaseTruncation;

    /** Sayfa başına üst sınırlar. */
    private const MAX_QUERIES = 40;

    private const MAX_PAGE_MS = 1500;

    /** Rapor ekranları geniş tarih aralığını (1 yıl) toplar; ayrı ve daha yüksek sınır. */
    private const MAX_REPORT_MS = 3000;

    private const MAX_EXPORT_MS = 20000;

    private array $results = [];

    public function test_pages_stay_within_budget_with_large_data(): void
    {
        ini_set('memory_limit', '2048M');
        $business = $this->makeBusiness();
        $started = microtime(true);
        $stats = (new VolumeSeeder)->seedBusiness($business, [
            'dealers' => (int) env('PERF_DEALERS', 300), 'products' => (int) env('PERF_PRODUCTS', 20000), 'orders' => (int) env('PERF_ORDERS', 50000),
        ]);
        $seedSeconds = round(microtime(true) - $started, 1);
        DB::statement('ANALYZE TABLE orders, order_items, products, stock_movements, current_account_movements, collections, warehouse_stock');

        [$rel, $order, $account] = app(TenantContext::class)->runAs($business->id, fn () => [
            DealerRelationship::orderBy('id')->first(),
            Order::where('status', 'completed')->latest('id')->first(),
            CurrentAccount::orderByDesc('balance')->first(),
        ]);
        $this->assertSame((string) DB::table('current_account_movements')->where('current_account_id', $account->id)->selectRaw('SUM(debit) - SUM(credit) s')->value('s'), (string) $account->balance);

        $this->actingAs($business->user, 'business');
        $pages = [
            'Dashboard (bu ay)' => route('business.dashboard'),
            'Dashboard (12 ay)' => route('business.dashboard', ['period' => '12m']),
            'Dashboard (12 ay, kategori)' => route('business.dashboard', ['period' => '12m', 'category' => DB::table('categories')->where('business_id', $business->id)->whereNull('parent_id')->value('id')]),
            'Siparişler' => route('business.orders.index'),
            'Siparişler (arama + durum)' => route('business.orders.index', ['q' => 'SIP-2026', 'status' => 'completed']),
            'Sipariş detayı' => route('business.orders.show', $order),
            'Ürünler' => route('business.products.index'),
            'Ürünler (arama)' => route('business.products.index', ['q' => 'Defter']),
            'Stok durumu' => route('business.stock.index'),
            'Stok hareketleri' => route('business.stock-movements.index'),
            'Bayiler' => route('business.dealers.index'),
            'Bayi detayı' => route('business.dealers.show', $rel),
            'Cari hesaplar' => route('business.accounts.index'),
            'Cari hesap ekstresi' => route('business.accounts.show', $account),
            'Tahsilatlar' => route('business.collections.index'),
        ];
        foreach (['orders', 'order-items', 'top-products', 'dealer-volume', 'account-balances', 'account-movements', 'collections', 'warehouse-stock', 'stock-movements', 'products', 'critical-stock'] as $key) {
            $pages["Rapor: {$key}"] = route('business.reports.show', ['key' => $key, 'from' => now()->subYear()->toDateString()]);
        }
        foreach ($pages as $label => $url) {
            // Dashboard ilk açılışta (önbellek boş) ölçülür; önbellekli hali ayrıca raporlanır.
            $isDashboard = str_starts_with($label, 'Dashboard');
            $this->measure('Toptancı', $label.($isDashboard ? ' — ilk açılış' : ''), $url,
                str_starts_with($label, 'Rapor') || $isDashboard ? self::MAX_REPORT_MS : self::MAX_PAGE_MS, false, $isDashboard);
            if ($isDashboard) {
                $this->measure('Toptancı', $label.' — önbellekten', $url, self::MAX_PAGE_MS);
            }
        }
        $this->measure('Toptancı', 'Excel: siparişler (1 yıl)', route('business.reports.export', ['key' => 'orders', 'format' => 'xlsx', 'from' => now()->subYear()->toDateString()]), self::MAX_EXPORT_MS, true);
        $this->measure('Toptancı', 'Excel: sipariş kalemleri (3 ay)', route('business.reports.export', ['key' => 'order-items', 'format' => 'xlsx', 'from' => now()->subMonths(3)->toDateString()]), self::MAX_EXPORT_MS, true);

        $dealerUser = DealerUser::where('dealer_id', $rel->dealer_id)->firstOrFail();
        $this->actingAs($dealerUser, 'dealer');
        foreach ([
            'Ana sayfa' => route('dealer.dashboard'),
            'Katalog' => route('dealer.catalog'),
            'Katalog (arama)' => route('dealer.catalog', ['q' => 'Kalem']),
            'Siparişlerim' => route('dealer.orders.index'),
            'Cari ekstre' => route('dealer.account.statement'),
            'Rapor: siparişlerim' => route('dealer.reports.show', ['key' => 'my-orders', 'from' => now()->subYear()->toDateString()]),
        ] as $label => $url) {
            $this->measure('Bayi', $label, $url, self::MAX_PAGE_MS);
        }

        $this->write($stats, $seedSeconds);

        $over = array_filter($this->results, fn ($r) => ! $r['ok']);
        $this->assertSame([], array_values(array_map(fn ($r) => "{$r['area']} / {$r['page']}: {$r['ms']} ms, {$r['queries']} sorgu (en yavaş sorgu {$r['slowest_ms']} ms)", $over)));
    }

    private function measure(string $area, string $label, string $url, int $maxMs, bool $streamed = false, bool $cold = false): void
    {
        $queries = 0;
        $slowest = 0.0;
        $slowestSql = '';
        Event::listen(QueryExecuted::class, function (QueryExecuted $q) use (&$queries, &$slowest, &$slowestSql) {
            $queries++;
            if ($q->time > $slowest) {
                [$slowest, $slowestSql] = [$q->time, $q->sql];
            }
        });

        $this->get($url); // ısınma (görünüm derleme)
        if ($cold) {
            Cache::flush();
        }
        $queries = 0;
        $slowest = 0.0;
        $t = microtime(true);
        $response = $this->get($url);
        if ($streamed) {
            $response->streamedContent();
        }
        $ms = (int) round((microtime(true) - $t) * 1000);
        $response->assertOk();
        Event::forget(QueryExecuted::class);

        $this->results[] = [
            'area' => $area, 'page' => $label, 'ms' => $ms, 'queries' => $queries, 'slowest_ms' => round($slowest, 1),
            'slowest_sql' => mb_substr($slowestSql, 0, 300),
            // Dışa aktarma satırları 1000'lik parçalarla okur; sorgu sayısı veri boyutuyla artar (N+1 değildir).
            'ok' => $ms <= $maxMs && ($streamed || $queries <= self::MAX_QUERIES),
        ];
    }

    private function write(array $stats, float $seedSeconds): void
    {
        $dir = storage_path('app/performans');
        @mkdir($dir, 0775, true);
        file_put_contents("{$dir}/sonuclar.json", json_encode(['data' => $stats, 'seed_seconds' => $seedSeconds, 'pages' => $this->results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $md = "| Alan | Sayfa | Süre (ms) | Sorgu | En yavaş sorgu (ms) |\n|---|---|---:|---:|---:|\n";
        foreach ($this->results as $r) {
            $md .= "| {$r['area']} | {$r['page']} | {$r['ms']} | {$r['queries']} | {$r['slowest_ms']} |\n";
        }
        file_put_contents("{$dir}/sonuclar.md", $md);
    }
}
