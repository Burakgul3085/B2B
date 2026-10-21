<?php

namespace App\Domain\Store\Reports;

use App\Models\Store\StoreOrder;
use App\Models\Store\StoreRefund;
use App\Models\Store\StoreReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Mağaza raporları (docs/21-magaza-m8-raporu.md §2). Tarihler Türkiye saatine göre gün olarak seçilir.
 * Satış sayılan siparişler: ödenmiş veya kapıda ödemeli olarak oluşturulmuş, iptal edilmemiş ve "teslim alınmadı" olmayanlar.
 * Tutarlar KDV dahildir. Net satış = sipariş tutarı − iade edilen tutar.
 */
class StoreReports
{
    public const SALE_STATUSES = ['paid', 'awaiting_approval', 'preparing', 'shipped', 'delivered'];

    private const TZ = 'Europe/Istanbul';

    /** @return array{0: Carbon, 1: Carbon} UTC sınırlar [başlangıç, bitiş) */
    public static function bounds(string $from, string $to): array
    {
        return [Carbon::parse($from, self::TZ)->startOfDay()->utc(), Carbon::parse($to, self::TZ)->addDay()->startOfDay()->utc()];
    }

    public function sales(string $from, string $to): Builder
    {
        return StoreOrder::query()->whereIn('status', self::SALE_STATUSES)->whereBetween('created_at', self::bounds($from, $to));
    }

    /** @return array<string, mixed> */
    public function summary(string $from, string $to): array
    {
        $row = $this->sales($from, $to)->selectRaw('COUNT(*) as orders, COALESCE(SUM(total),0) as gross, COALESCE(SUM(discount_total),0) as discounts,
            COALESCE(SUM(shipping_fee + cod_fee),0) as fees, COALESCE(SUM(refunded_total),0) as refunded')->first();
        [$start, $end] = self::bounds($from, $to);
        $items = (int) $this->sales($from, $to)->join('store_order_items', 'store_order_items.order_id', '=', 'store_orders.id')->sum('store_order_items.quantity');

        return [
            'orders' => (int) $row->orders,
            'gross' => bcadd((string) $row->gross, '0', 2),
            'discounts' => bcadd((string) $row->discounts, '0', 2),
            'fees' => bcadd((string) $row->fees, '0', 2),
            'refunded' => bcadd((string) $row->refunded, '0', 2),
            'net' => bcsub((string) $row->gross, (string) $row->refunded, 2),
            'average' => $row->orders > 0 ? bcdiv((string) $row->gross, (string) $row->orders, 2) : '0.00',
            'items' => $items,
            'cancelled' => StoreOrder::whereIn('status', ['cancelled', 'not_delivered'])->whereBetween('created_at', [$start, $end])->count(),
            // Dönemde yapılan para iadeleri (sipariş tarihinden bağımsız)
            'refunds_in_period' => bcadd((string) StoreRefund::where('status', 'succeeded')->whereBetween('created_at', [$start, $end])->sum('amount'), '0', 2),
        ];
    }

    /** @return list<array{day: string, orders: int, gross: string}> Türkiye saatine göre günlük */
    public function daily(string $from, string $to): array
    {
        $rows = $this->sales($from, $to)
            ->selectRaw('DATE(DATE_ADD(created_at, INTERVAL 3 HOUR)) as day, COUNT(*) as orders, SUM(total) as gross')
            ->groupBy('day')->orderBy('day')->get()->keyBy('day');

        $out = [];
        for ($d = Carbon::parse($from); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $r = $rows[$d->toDateString()] ?? null;
            $out[] = ['day' => $d->toDateString(), 'orders' => (int) ($r->orders ?? 0), 'gross' => bcadd((string) ($r->gross ?? 0), '0', 2)];
        }

        return $out;
    }

    /** @return list<object{payment_method: string, delivery_method: string, orders: int, gross: string}> */
    public function byMethod(string $from, string $to): array
    {
        return $this->sales($from, $to)->selectRaw('payment_method, delivery_method, COUNT(*) as orders, SUM(total) as gross')
            ->groupBy('payment_method', 'delivery_method')->orderByDesc('gross')->get()->all();
    }

    /** En çok satan ürünler (adet), net ürün cirosuyla (indirim payı düşülmüş). */
    public function topProducts(string $from, string $to, int $limit = 10): array
    {
        return $this->sales($from, $to)->join('store_order_items as i', 'i.order_id', '=', 'store_orders.id')
            ->selectRaw('i.sku, MAX(i.product_name) as name, MAX(i.option_label) as option_label, SUM(i.quantity) as quantity, SUM(i.line_total - i.discount_amount) as revenue')
            ->groupBy('i.sku')->orderByDesc('quantity')->orderByDesc('revenue')->limit($limit)->get()->all();
    }

    public function coupons(string $from, string $to): array
    {
        return $this->sales($from, $to)->whereNotNull('discount_name')
            ->selectRaw('discount_name, discount_code, COUNT(*) as orders, SUM(discount_total) as amount, SUM(total) as gross')
            ->groupBy('discount_name', 'discount_code')->orderByDesc('orders')->get()->all();
    }

    /** @return array<string, int> iade nedeni => talep sayısı (reddedilenler hariç) */
    public function returnReasons(string $from, string $to): array
    {
        return StoreReturn::where('status', '!=', 'rejected')->whereBetween('created_at', self::bounds($from, $to))
            ->selectRaw('reason, COUNT(*) as c')->groupBy('reason')->orderByDesc('c')->pluck('c', 'reason')->map(fn ($c) => (int) $c)->all();
    }
}
