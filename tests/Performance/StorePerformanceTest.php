<?php

namespace Tests\Performance;

use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreSetting;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Mağaza — büyük veriyle vitrin ve yönetim sayfa süreleri, sorgu sayıları.
 * Çalıştırma: php artisan test --group=performance   (normal test koşusuna dahil değildir)
 * Sonuçlar: storage/app/performans/magaza.json ve .md
 */
#[Group('performance')]
class StorePerformanceTest extends TestCase
{
    use DatabaseTruncation;

    /** Migration'la yüklenen veriler boşaltılmaz. */
    protected $exceptTables = ['migrations', 'tr_provinces', 'tr_districts', 'store_legal_documents'];

    private const MAX_QUERIES = 40;

    private const MAX_PAGE_MS = 1500;

    private const MAX_REPORT_MS = 3000;

    private array $results = [];

    public function test_store_pages_stay_within_budget_with_large_data(): void
    {
        ini_set('memory_limit', '2048M');
        $sizes = ['products' => (int) env('PERF_STORE_PRODUCTS', 10000), 'customers' => (int) env('PERF_STORE_CUSTOMERS', 5000), 'orders' => (int) env('PERF_STORE_ORDERS', 30000)];
        $started = microtime(true);
        [$categorySlug, $productSlug, $customerId] = $this->seedVolume($sizes);
        $seedSeconds = round(microtime(true) - $started, 1);
        DB::statement('ANALYZE TABLE store_products, store_product_variants, store_categories, store_orders, store_order_items, store_customers');
        StoreSetting::put(['is_open' => '1']);

        $order = StoreOrder::where('customer_id', $customerId)->latest('id')->firstOrFail();
        $from = now()->subYear()->toDateString();

        foreach ([
            'Ana sayfa' => route('store.home'),
            'Tüm ürünler' => route('store.products'),
            'Tüm ürünler (fiyat sırası, 20. sayfa)' => route('store.products', ['sirala' => 'fiyat-artan', 'page' => 20]),
            'Kategori' => route('store.category', $categorySlug),
            'Arama' => route('store.search', ['q' => 'defter']),
            'Ürün detayı' => route('store.product', $productSlug),
            'Site haritası' => route('store.sitemap'),
        ] as $label => $url) {
            $this->measure('Vitrin', $label, $url, self::MAX_PAGE_MS);
        }

        $this->actingAs(StoreCustomer::findOrFail($customerId), 'customer');
        $this->measure('Müşteri', 'Hesabım (sipariş geçmişi)', route('store.account'), self::MAX_PAGE_MS);

        $this->flushSession();
        $this->actingAs(StoreAdmin::create(['name' => 'Perf', 'email' => 'perf@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
        foreach ([
            'Panel' => route('store_admin.dashboard'),
            'Siparişler' => route('store_admin.orders.index'),
            'Siparişler (arama)' => route('store_admin.orders.index', ['q' => 'Yılmaz']),
            'Sipariş detayı' => route('store_admin.orders.show', $order),
            'Ürünler' => route('store_admin.products.index'),
            'Ürünler (arama)' => route('store_admin.products.index', ['q' => 'Kalem']),
            'Stok' => route('store_admin.stock.index'),
        ] as $label => $url) {
            $this->measure('Yönetim', $label, $url, self::MAX_PAGE_MS);
        }
        $this->measure('Yönetim', 'Raporlar (1 yıl)', route('store_admin.reports.index', ['from' => $from]), self::MAX_REPORT_MS);

        $this->write($sizes, $seedSeconds);

        $over = array_filter($this->results, fn ($r) => ! $r['ok']);
        $this->assertSame([], array_values(array_map(fn ($r) => "{$r['area']} / {$r['page']}: {$r['ms']} ms, {$r['queries']} sorgu (en yavaş sorgu {$r['slowest_ms']} ms)", $over)));
    }

    /** @return array{0: string, 1: string, 2: int} kategori slug'ı, ürün slug'ı, çok siparişli müşteri */
    private function seedVolume(array $sizes): array
    {
        $now = now()->toDateTimeString();
        $words = ['Defter', 'Kalem', 'Silgi', 'Boya', 'Dosya', 'Klasör', 'Cetvel', 'Makas', 'Yapıştırıcı', 'Ajanda'];

        $parents = [];
        foreach ($words as $i => $word) {
            $parents[] = DB::table('store_categories')->insertGetId(['name' => $word, 'slug' => Str::slug($word), 'sort' => $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $categories = $parents;
        foreach ($parents as $i => $parentId) {
            for ($j = 1; $j <= 4; $j++) {
                $categories[] = DB::table('store_categories')->insertGetId(['parent_id' => $parentId, 'name' => "{$words[$i]} {$j}", 'slug' => Str::slug("{$words[$i]}-alt-{$j}"), 'sort' => $j, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach (array_chunk(range(1, $sizes['products']), 1000) as $chunk) {
            $products = [];
            foreach ($chunk as $n) {
                $name = $words[$n % 10]." Model {$n}";
                $products[] = ['name' => $name, 'slug' => Str::slug($name), 'category_id' => $categories[$n % count($categories)], 'is_published' => true,
                    'is_featured' => $n % 50 === 0, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('store_products')->insert($products);
            $firstId = (int) DB::table('store_products')->where('slug', $products[0]['slug'])->value('id');
            $rows = [];
            foreach ($chunk as $k => $n) {
                foreach ($n % 5 === 0 ? ['Mavi', 'Kırmızı', 'Siyah'] : [null] as $s => $option) {
                    $rows[] = ['product_id' => $firstId + $k, 'sku' => "PRF-{$n}-{$s}", 'option_value' => $option, 'price' => 10 + ($n % 400), 'vat_rate' => 20,
                        'stock_quantity' => $n % 7 === 0 ? 0 : 50, 'critical_stock' => 5, 'is_active' => true, 'sort' => $s, 'created_at' => $now, 'updated_at' => $now];
                }
            }
            DB::table('store_product_variants')->insert($rows);
        }
        $variantIds = DB::table('store_product_variants')->orderBy('id')->limit(2000)->get(['id', 'product_id', 'sku', 'price'])->all();
        $variants = array_map(fn ($v) => [$v->id, $v->product_id, $v->sku, (float) $v->price], $variantIds);

        $password = Hash::make('perf-parola');
        foreach (array_chunk(range(1, $sizes['customers']), 1000) as $chunk) {
            DB::table('store_customers')->insert(array_map(fn ($n) => ['first_name' => 'Müşteri', 'last_name' => "Yılmaz {$n}", 'email' => "musteri{$n}@ornek.com",
                'phone' => '0532'.str_pad((string) $n, 7, '0', STR_PAD_LEFT), 'password' => $password, 'is_active' => true, 'kvkk_accepted_at' => $now, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }
        $customerIds = DB::table('store_customers')->orderBy('id')->pluck('id')->all();
        $this->ensureGeography();
        $district = DB::table('tr_districts')->where('province_id', 26)->first(['id', 'name']);
        $statuses = ['delivered', 'delivered', 'delivered', 'shipped', 'preparing', 'cancelled'];

        foreach (array_chunk(range(1, $sizes['orders']), 1000) as $chunk) {
            $orders = [];
            $lines = [];
            foreach ($chunk as $n) {
                // İlk müşterinin çok siparişi var (hesap sayfası en kötü durum).
                $customerId = $n % 10 === 0 ? $customerIds[0] : $customerIds[$n % count($customerIds)];
                $created = now()->subMinutes(($sizes['orders'] - $n) * 15)->toDateTimeString();
                $items = [];
                $subtotal = 0;
                for ($l = 0; $l < 1 + $n % 4; $l++) {
                    [$vid, $pid, $sku, $price] = $variants[($n * 7 + $l) % count($variants)];
                    $qty = 1 + $l;
                    $total = round($price * $qty, 2);
                    $subtotal += $total;
                    $items[] = ['variant_id' => $vid, 'product_id' => $pid, 'product_name' => "Ürün {$pid}", 'sku' => $sku, 'unit_price' => $price, 'vat_rate' => 20,
                        'quantity' => $qty, 'line_total' => $total, 'discount_amount' => 0, 'vat_amount' => round($total / 6, 2)];
                }
                $status = $statuses[$n % count($statuses)];
                $orders[] = ['order_no' => 'MGZ-'.str_pad((string) $n, 8, '0', STR_PAD_LEFT), 'access_token' => Str::random(40), 'customer_id' => $customerId,
                    'email' => "musteri{$n}@ornek.com", 'phone' => '05320000000', 'status' => $status, 'payment_method' => $n % 3 ? 'card' : 'cod',
                    'payment_status' => $status === 'cancelled' ? 'void' : 'paid', 'delivery_method' => $n % 3 ? 'cargo' : 'local',
                    'shipping_name' => "Ayşe Yılmaz {$n}", 'shipping_phone' => '05320000000', 'shipping_province_id' => 26, 'shipping_district_id' => $district->id,
                    'shipping_province' => 'Eskişehir', 'shipping_district' => $district->name, 'shipping_address' => "Atatürk Bulvarı No {$n}",
                    'billing_type' => 'individual', 'billing_name' => "Ayşe Yılmaz {$n}", 'billing_address' => "Atatürk Bulvarı No {$n}",
                    'subtotal' => $subtotal, 'total' => $subtotal, 'vat_total' => round($subtotal / 6, 2), 'paid_at' => $created, 'created_at' => $created, 'updated_at' => $created];
                $lines[$n] = $items;
            }
            DB::table('store_orders')->insert($orders);
            $ids = DB::table('store_orders')->whereIn('order_no', array_column($orders, 'order_no'))->pluck('id', 'order_no');
            $rows = [];
            foreach ($lines as $n => $items) {
                foreach ($items as $item) {
                    $rows[] = ['order_id' => $ids['MGZ-'.str_pad((string) $n, 8, '0', STR_PAD_LEFT)]] + $item;
                }
            }
            DB::table('store_order_items')->insert($rows);
        }

        $category = DB::table('store_categories')->whereNull('parent_id')->value('slug');
        $product = DB::table('store_products')->where('id', $variants[0][1])->value('slug');

        return [$category, $product, $customerIds[0]];
    }

    private function ensureGeography(): void
    {
        if (! DB::table('tr_districts')->exists()) {
            $data = json_decode(file_get_contents(database_path('data/turkiye-il-ilce.json')), true, flags: JSON_THROW_ON_ERROR);
            foreach ($data as $plate => $province) {
                DB::table('tr_provinces')->insert(['id' => (int) $plate, 'name' => $province['name']]);
                DB::table('tr_districts')->insert(array_map(fn ($name) => ['province_id' => (int) $plate, 'name' => $name], $province['districts']));
            }
        }
    }

    private function measure(string $area, string $label, string $url, int $maxMs): void
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
        $queries = 0;
        $slowest = 0.0;
        $t = microtime(true);
        $response = $this->get($url);
        $ms = (int) round((microtime(true) - $t) * 1000);
        $this->assertSame(200, $response->status(), "{$label}: ".$response->headers->get('Location'));
        Event::forget(QueryExecuted::class);

        $this->results[] = [
            'area' => $area, 'page' => $label, 'ms' => $ms, 'queries' => $queries, 'slowest_ms' => round($slowest, 1),
            'slowest_sql' => mb_substr($slowestSql, 0, 300), 'ok' => $ms <= $maxMs && $queries <= self::MAX_QUERIES,
        ];
    }

    private function write(array $sizes, float $seedSeconds): void
    {
        $dir = storage_path('app/performans');
        @mkdir($dir, 0775, true);
        file_put_contents("{$dir}/magaza.json", json_encode(['data' => $sizes, 'seed_seconds' => $seedSeconds, 'pages' => $this->results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $md = "| Alan | Sayfa | Süre (ms) | Sorgu | En yavaş sorgu (ms) |\n|---|---|---:|---:|---:|\n";
        foreach ($this->results as $r) {
            $md .= "| {$r['area']} | {$r['page']} | {$r['ms']} | {$r['queries']} | {$r['slowest_ms']} |\n";
        }
        file_put_contents("{$dir}/magaza.md", $md);
    }
}
