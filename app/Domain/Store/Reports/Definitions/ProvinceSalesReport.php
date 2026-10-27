<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use Illuminate\Database\Eloquent\Builder;

/** İl bazında satış: hangi illerden ne kadar sipariş geldiği. */
class ProvinceSalesReport extends StoreReport
{
    public function key(): string
    {
        return 'il-bazinda-satis';
    }

    public function title(): string
    {
        return 'İl bazında satış';
    }

    public function description(): string
    {
        return 'Teslimat adresinin iline göre sipariş, müşteri sayısı, ciro ve cirodaki pay.';
    }

    public function group(): string
    {
        return 'Satış';
    }

    public function icon(): string
    {
        return 'map-pin';
    }

    public function filters(): array
    {
        return ReportOptions::dates('year') + ['delivery' => ReportOptions::delivery()];
    }

    public function columns(): array
    {
        return [
            'province' => ['label' => 'İl', 'type' => 'text'],
            'orders' => ['label' => 'Sipariş', 'type' => 'int', 'total' => true],
            'customers' => ['label' => 'Müşteri', 'type' => 'int'],
            'gross' => ['label' => 'Sipariş tutarı', 'type' => 'money', 'total' => true],
            'net' => ['label' => 'Net satış', 'type' => 'money', 'total' => true],
            'average' => ['label' => 'Ortalama sepet', 'type' => 'money'],
            'share' => ['label' => 'Cirodaki pay', 'type' => 'percent'],
        ];
    }

    public function defaultSort(): array
    {
        return ['gross', 'desc'];
    }

    public function query(array $f): Builder
    {
        return OrdersReport::base(['status' => 'sales'] + $f)
            ->selectRaw('store_orders.shipping_province as province, COUNT(*) as orders, COUNT(DISTINCT store_orders.email) as customers')
            ->selectRaw('SUM(store_orders.total) as gross, SUM(store_orders.total - store_orders.refunded_total) as net, AVG(store_orders.total) as average')
            ->selectRaw('100 * SUM(store_orders.total) / NULLIF(SUM(SUM(store_orders.total)) OVER (), 0) as share')
            ->groupBy('store_orders.shipping_province');
    }
}
