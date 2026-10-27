<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use App\Models\Store\StoreOrder;
use Illuminate\Database\Eloquent\Builder;

/** Ödeme ve teslimat yöntemleri: sipariş sayısı, tutar ve cirodaki pay. */
class PaymentDeliveryReport extends StoreReport
{
    public function key(): string
    {
        return 'odeme-teslimat';
    }

    public function title(): string
    {
        return 'Ödeme ve teslimat yöntemleri';
    }

    public function description(): string
    {
        return 'Kart, havale ve kapıda ödeme ile kargo ve mağaza teslimatının sipariş sayısı, tutarı ve cirodaki payı.';
    }

    public function group(): string
    {
        return 'Satış';
    }

    public function icon(): string
    {
        return 'lock';
    }

    public function filters(): array
    {
        return ['by' => ['label' => 'Gruplama', 'type' => 'select', 'default' => 'both', 'options' => ['both' => 'Ödeme ve teslimat', 'payment' => 'Yalnızca ödeme', 'delivery' => 'Yalnızca teslimat']]]
            + ReportOptions::dates();
    }

    public function columns(): array
    {
        return [
            'payment_label' => ['label' => 'Ödeme', 'type' => 'text'],
            'delivery_label' => ['label' => 'Teslimat', 'type' => 'text'],
            'orders' => ['label' => 'Sipariş', 'type' => 'int', 'total' => true],
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
        $by = $f['by'] ?? 'both';
        $payment = $by === 'delivery' ? "'Tümü'" : ReportOptions::label('store_orders.payment_method', StoreOrder::PAYMENT_METHODS);
        $delivery = $by === 'payment' ? "'Tümü'" : ReportOptions::label('store_orders.delivery_method', StoreOrder::DELIVERY_METHODS);

        return OrdersReport::base(['status' => 'sales'] + $f)
            ->selectRaw("{$payment} as payment_label, {$delivery} as delivery_label")
            ->selectRaw('COUNT(*) as orders, SUM(store_orders.total) as gross, SUM(store_orders.total - store_orders.refunded_total) as net, AVG(store_orders.total) as average')
            ->selectRaw('100 * SUM(store_orders.total) / NULLIF(SUM(SUM(store_orders.total)) OVER (), 0) as share')
            ->groupBy('payment_label', 'delivery_label');
    }
}
