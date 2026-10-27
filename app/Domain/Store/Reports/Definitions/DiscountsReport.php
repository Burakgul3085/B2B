<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use Illuminate\Database\Eloquent\Builder;

/** Kampanya ve kuponlar: kullanım sayısı, verilen indirim ve getirdiği satış. */
class DiscountsReport extends StoreReport
{
    public function key(): string
    {
        return 'kampanya-kuponlar';
    }

    public function title(): string
    {
        return 'Kampanya ve kuponlar';
    }

    public function description(): string
    {
        return 'Her kampanya ve kuponun kullanıldığı sipariş sayısı, verilen indirim, getirdiği satış ve ortalama sepet.';
    }

    public function group(): string
    {
        return 'Pazarlama';
    }

    public function icon(): string
    {
        return 'percent';
    }

    public function filters(): array
    {
        return ReportOptions::dates('year');
    }

    public function columns(): array
    {
        return [
            'discount_name' => ['label' => 'Kampanya / kupon', 'type' => 'text'],
            'discount_code' => ['label' => 'Kupon kodu', 'type' => 'text'],
            'orders' => ['label' => 'Sipariş', 'type' => 'int', 'total' => true],
            'customers' => ['label' => 'Müşteri', 'type' => 'int'],
            'amount' => ['label' => 'Verilen indirim', 'type' => 'money', 'total' => true],
            'gross' => ['label' => 'Sipariş tutarı', 'type' => 'money', 'total' => true],
            'average' => ['label' => 'Ortalama sepet', 'type' => 'money'],
            'rate' => ['label' => 'İndirim oranı', 'type' => 'percent'],
        ];
    }

    public function defaultSort(): array
    {
        return ['orders', 'desc'];
    }

    public function query(array $f): Builder
    {
        return OrdersReport::base(['status' => 'sales'] + $f)
            ->whereNotNull('store_orders.discount_name')
            ->groupBy('store_orders.discount_name', 'store_orders.discount_code')
            ->select('store_orders.discount_name', 'store_orders.discount_code')
            ->selectRaw('COUNT(*) as orders, COUNT(DISTINCT store_orders.email) as customers, SUM(store_orders.discount_total) as amount')
            ->selectRaw('SUM(store_orders.total) as gross, AVG(store_orders.total) as average')
            ->selectRaw('100 * SUM(store_orders.discount_total) / NULLIF(SUM(store_orders.subtotal), 0) as rate');
    }
}
