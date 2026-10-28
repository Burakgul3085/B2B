<?php

namespace App\Domain\Reporting;

use App\Domain\Reporting\Reports\Options;
use App\Domain\Support\LocalTime;
use App\Domain\Tenancy\TenantContext;
use App\Models\CollectionEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard grafik ve göstergeleri — yalnızca gerçek veriden (Bölüm 12). Filtreler (dönem, bayi, kategori)
 * hem göstergelere hem grafiklere aynı şekilde uygulanır.
 *
 * Tanımlar: "Satış" = onaylanmış ve iptal edilmemiş siparişlerin KDV dahil genel toplamı, onay tarihine göre.
 * Kategori filtresi yalnızca kalem bazlı ölçülere (kategori dağılımı ve kategoriye göre satış) uygulanabilir;
 * sipariş tutarı bir siparişin tamamıdır.
 */
class DashboardMetrics
{
    public const FULFILLED = ['approved', 'preparing', 'completed', 'cancel_requested'];

    public const PERIODS = ['month' => 'Bu ay', '30d' => 'Son 30 gün', '90d' => 'Son 90 gün', '12m' => 'Son 12 ay', 'custom' => 'Özel aralık'];

    /** @return array{from: Carbon, to: Carbon, prevFrom: Carbon, prevTo: Carbon, monthly: bool} */
    public function range(string $period, ?string $from = null, ?string $to = null): array
    {
        $now = LocalTime::now();
        [$start, $end] = match ($period) {
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            '90d' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            '12m' => [$now->copy()->subMonths(11)->startOfMonth(), $now->copy()->endOfDay()],
            'custom' => [Carbon::parse($from ?? $now->copy()->startOfMonth(), LocalTime::TZ)->startOfDay(), Carbon::parse($to ?? $now, LocalTime::TZ)->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };
        if ($start > $end) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $days = (int) $start->diffInDays($end) + 1;

        return [
            'from' => $start, 'to' => $end,
            'prevFrom' => $start->copy()->subDays($days), 'prevTo' => $start->copy()->subSecond(),
            'monthly' => $days > 92,
        ];
    }

    /**
     * Dashboard'un tüm sipariş ölçüleri. Uzun dönemlerde (12 ay, binlerce sipariş) toplama pahalı olduğu için
     * sonuç önbelleğe alınır; anahtar işletme + filtreler + son sipariş değişikliğidir. Herhangi bir sipariş
     * oluşturulduğunda veya durumu değiştiğinde anahtar değişir, yani eski veri gösterilmez. Son 5 saniyede
     * değişiklik varsa (aynı saniye içindeki ardışık güncellemeler) önbellek kullanılmaz. Tahsilat her zaman canlıdır.
     *
     * @return array{kpis: array, series: array, status: array, categories: array, dealers: array}
     */
    public function snapshot(array $range, array $f): array
    {
        $build = fn () => [
            'kpis' => $this->kpis($range, $f),
            'series' => $this->salesSeries($range, $f),
            'status' => $this->statusGroups($range, $f),
            'categories' => $this->categories($range, $f),
            'dealers' => $this->dealers($range, $f),
        ];

        $lastChange = Order::max('updated_at');
        if ($lastChange === null || Carbon::parse($lastChange)->gt(now()->subSeconds(5))) {
            return $build();
        }

        $key = sprintf('dashboard:%d:%s:%s', app(TenantContext::class)->businessId(),
            md5(json_encode([$f['dealer'] ?? null, $f['category'] ?? null, $range['from']->toDateTimeString(), $range['to']->toDateTimeString()])),
            Carbon::parse($lastChange)->format('YmdHis'));
        $data = Cache::remember($key, now()->addHour(), $build);
        $data['kpis']['collections'] = $this->collections($range, $f);

        return $data;
    }

    /** Dönem göstergeleri ve önceki dönemle karşılaştırma. */
    public function kpis(array $range, array $f): array
    {
        $current = $this->sales($range['from'], $range['to'], $f);
        $previous = $this->sales($range['prevFrom'], $range['prevTo'], $f);
        $collections = $this->collections($range, $f);

        return [
            'sales' => $current['amount'],
            'sales_prev' => $previous['amount'],
            'orders' => $current['count'],
            'orders_prev' => $previous['count'],
            'average' => $current['count'] ? bcdiv($current['amount'], (string) $current['count'], 2) : '0.00',
            'collections' => $collections,
        ];
    }

    /** Dönemde iptal edilmemiş tahsilat toplamı. */
    private function collections(array $range, array $f): string
    {
        return (string) CollectionEntry::whereNull('cancelled_at')
            ->whereBetween('collected_on', [$range['from']->toDateString(), $range['to']->toDateString()])
            ->when($f['dealer'] ?? null, fn ($q, $d) => $q->whereHas('account', fn ($a) => $a->where('dealer_relationship_id', $d)))
            ->sum('amount');
    }

    /**
     * Satış eğrisi: dönem ve önceki dönem (aynı uzunlukta) — günlük veya aylık kova.
     *
     * @return array{labels: string[], current: float[], previous: float[], table: array}
     */
    public function salesSeries(array $range, array $f): array
    {
        $monthly = $range['monthly'];
        $current = $this->bucketed($range['from'], $range['to'], $f, $monthly);
        $previous = $this->bucketed($range['prevFrom'], $range['prevTo'], $f, $monthly);

        $labels = [];
        $cur = [];
        $prev = [];
        $cursor = $range['from']->copy();
        $prevCursor = $range['prevFrom']->copy();
        $i = 0;
        while ($cursor <= $range['to'] && $i < 400) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $prevKey = $monthly ? $prevCursor->format('Y-m') : $prevCursor->format('Y-m-d');
            $labels[] = $monthly ? $cursor->translatedFormat('M Y') : $cursor->format('d.m');
            $cur[] = (float) ($current[$key] ?? 0);
            $prev[] = (float) ($previous[$prevKey] ?? 0);
            $monthly ? ($cursor->addMonthNoOverflow() && $prevCursor->addMonthNoOverflow()) : ($cursor->addDay() && $prevCursor->addDay());
            $i++;
        }

        return ['labels' => $labels, 'current' => $cur, 'previous' => $prev, 'monthly' => $monthly];
    }

    /** Sipariş durumları, dört grupta (dönemde oluşturulan siparişler). */
    public function statusGroups(array $range, array $f): array
    {
        $groups = [
            'Karar bekleyen' => ['submitted', 'in_review', 'cancel_requested'],
            'Onaylandı / hazırlanıyor' => ['approved', 'preparing'],
            'Tamamlandı' => ['completed'],
            'Reddedildi / iptal' => ['rejected', 'cancelled'],
        ];
        $counts = $this->orders($f)->whereBetween('orders.created_at', [LocalTime::utc($range['from']), LocalTime::utc($range['to'])])
            ->selectRaw('orders.status, COUNT(*) c')->groupBy('orders.status')->pluck('c', 'status');

        return collect($groups)->map(fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s] ?? 0))->all();
    }

    /** Ana kategoriye göre net satış (kalem bazlı). @return array<string, float> */
    public function categories(array $range, array $f, int $limit = 8): array
    {
        return $this->items($range, $f)
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->selectRaw("COALESCE(p.name, c.name, 'Kategorisiz') as label, SUM(order_items.line_net) as total")
            ->groupBy('label')->orderByDesc('total')->limit($limit)
            ->pluck('total', 'label')->map(fn ($v) => (float) $v)->all();
    }

    /** En çok satış yapılan bayiler. @return array<string, float> */
    public function dealers(array $range, array $f, int $limit = 8): array
    {
        return $this->fulfilled($range['from'], $range['to'], $f)
            ->join('dealer_relationships', 'dealer_relationships.id', '=', 'orders.dealer_relationship_id')
            ->join('dealers', 'dealers.id', '=', 'dealer_relationships.dealer_id')
            ->selectRaw('dealers.legal_name as label, SUM(orders.grand_total) as total')
            ->groupBy('dealers.legal_name')->orderByDesc('total')->limit($limit)
            ->pluck('total', 'label')->map(fn ($v) => (float) $v)->all();
    }

    /** Bayi paneli: son 12 ayın onaylanmış sipariş tutarı. */
    public function dealerMonthly(int $relationshipId): array
    {
        $from = LocalTime::now()->subMonths(11)->startOfMonth();
        $data = Order::where('dealer_relationship_id', $relationshipId)->whereIn('status', self::FULFILLED)
            ->where('approved_at', '>=', LocalTime::utc($from))
            ->selectRaw('DATE_FORMAT('.LocalTime::sql('approved_at').", '%Y-%m') k, SUM(grand_total) t")->groupBy('k')->pluck('t', 'k');

        $labels = [];
        $values = [];
        for ($m = $from->copy(); $m <= LocalTime::now(); $m->addMonthNoOverflow()) {
            $labels[] = $m->translatedFormat('M Y');
            $values[] = (float) ($data[$m->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @var array<int, int[]> */
    private array $categoryIds = [];

    /** Kategori ve alt kategorileri; bir istekte her ölçü için yeniden sorgulanmaz. */
    private function categoryIds(int $id): array
    {
        return $this->categoryIds[$id] ??= Options::categoryIds($id);
    }

    private function orders(array $f): Builder
    {
        return Order::query()
            ->when($f['dealer'] ?? null, fn ($q, $d) => $q->where('orders.dealer_relationship_id', $d))
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereExists(fn ($e) => $e->selectRaw('1')->from('order_items')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->whereIn('products.category_id', $this->categoryIds((int) $c))));
    }

    private function categoryProducts(int $category): Builder
    {
        return Product::whereIn('category_id', $this->categoryIds($category))->select('id');
    }

    private function fulfilled(Carbon $from, Carbon $to, array $f): Builder
    {
        return $this->orders($f)->whereIn('orders.status', self::FULFILLED)->whereBetween('orders.approved_at', [LocalTime::utc($from), LocalTime::utc($to)]);
    }

    private function items(array $range, array $f): Builder
    {
        return OrderItem::query()
            ->whereIn('order_items.order_id', $this->fulfilled($range['from'], $range['to'], array_diff_key($f, ['category' => 1]))->select('orders.id'))
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('order_items.product_id', $this->categoryProducts((int) $c)));
    }

    /** @return array{amount: string, count: int} */
    private function sales(Carbon $from, Carbon $to, array $f): array
    {
        $row = $this->fulfilled($from, $to, $f)->selectRaw('COALESCE(SUM(orders.grand_total), 0) a, COUNT(*) c')->toBase()->first();

        return ['amount' => bcadd((string) $row->a, '0', 2), 'count' => (int) $row->c];
    }

    private function bucketed(Carbon $from, Carbon $to, array $f, bool $monthly)
    {
        $format = $monthly ? '%Y-%m' : '%Y-%m-%d';
        // Kovalar, dönem sınırlarıyla aynı saat diliminde (Türkiye saati) hesaplanır.
        $expr = 'DATE_FORMAT('.LocalTime::sql('orders.approved_at').", '{$format}')";

        return $this->fulfilled($from, $to, $f)->selectRaw("{$expr} k, SUM(orders.grand_total) t")->groupBy(DB::raw($expr))->pluck('t', 'k');
    }
}
