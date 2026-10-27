<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Dönemsel satış: gün, hafta veya ay bazında sipariş, ciro, indirim, iade ve ortalama sepet. */
class PeriodSalesReport extends StoreReport
{
    private const FORMATS = ['day' => '%Y-%m-%d', 'week' => '%x-%v', 'month' => '%Y-%m'];

    private const MONTHS = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

    public function key(): string
    {
        return 'donemsel-satis';
    }

    public function title(): string
    {
        return 'Dönemsel satış';
    }

    public function description(): string
    {
        return 'Gün, hafta veya ay bazında sipariş sayısı, ciro, indirim, iade, net satış ve ortalama sepet.';
    }

    public function group(): string
    {
        return 'Satış';
    }

    public function icon(): string
    {
        return 'layers';
    }

    public function filters(): array
    {
        return ['period' => ['label' => 'Gruplama', 'type' => 'select', 'default' => 'day', 'options' => ['day' => 'Günlük', 'week' => 'Haftalık', 'month' => 'Aylık']]]
            + ReportOptions::dates()
            + ['payment' => ReportOptions::payment(), 'delivery' => ReportOptions::delivery()];
    }

    public function columns(): array
    {
        return [
            'period' => ['label' => 'Dönem', 'type' => 'text'],
            'orders' => ['label' => 'Sipariş', 'type' => 'int', 'total' => true],
            'items' => ['label' => 'Ürün adedi', 'type' => 'int', 'total' => true],
            'customers' => ['label' => 'Müşteri', 'type' => 'int', 'default' => false],
            'gross' => ['label' => 'Sipariş tutarı', 'type' => 'money', 'total' => true],
            'discounts' => ['label' => 'İndirim', 'type' => 'money', 'total' => true],
            'fees' => ['label' => 'Teslimat ve hizmet', 'type' => 'money', 'total' => true, 'default' => false],
            'refunded' => ['label' => 'İade', 'type' => 'money', 'total' => true],
            'net' => ['label' => 'Net satış', 'type' => 'money', 'total' => true],
            'average' => ['label' => 'Ortalama sepet', 'type' => 'money'],
        ];
    }

    public function query(array $f): Builder
    {
        $format = self::FORMATS[$f['period'] ?? 'day'] ?? self::FORMATS['day'];

        return OrdersReport::base(['status' => 'sales'] + $f)
            ->selectRaw('DATE_FORMAT('.ReportOptions::local('store_orders.created_at').", '{$format}') as period")
            ->selectRaw('COUNT(*) as orders, COUNT(DISTINCT store_orders.email) as customers')
            ->selectRaw('SUM((SELECT COALESCE(SUM(i.quantity), 0) FROM store_order_items i WHERE i.order_id = store_orders.id)) as items')
            ->selectRaw('SUM(store_orders.total) as gross, SUM(store_orders.discount_total) as discounts, SUM(store_orders.shipping_fee + store_orders.cod_fee) as fees')
            ->selectRaw('SUM(store_orders.refunded_total) as refunded, SUM(store_orders.total - store_orders.refunded_total) as net, AVG(store_orders.total) as average')
            ->groupBy('period');
    }

    /** Dönem anahtarı okunur etikete çevrilir (sıralama anahtar üzerinden yapıldığı için doğru kalır). */
    public function transform(Collection $rows, array $filters): Collection
    {
        return $rows->each(function ($row) use ($filters) {
            $key = (string) $row->period;
            $row->period = match ($filters['period'] ?? 'day') {
                'month' => self::MONTHS[(int) substr($key, 5, 2)].' '.substr($key, 0, 4),
                'week' => self::week($key),
                default => Carbon::parse($key)->format('d.m.Y'),
            };
        });
    }

    private static function week(string $key): string
    {
        [$year, $week] = array_map('intval', explode('-', $key));
        $start = Carbon::now()->setISODate($year, $week)->startOfWeek();

        return $start->format('d.m').'–'.$start->copy()->addDays(6)->format('d.m.Y')." ({$week}. hafta)";
    }
}
