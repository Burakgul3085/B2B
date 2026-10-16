<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/** Bayi: kendi siparişleri (yalnızca seçili toptancı). */
class MyOrdersReport extends Report
{
    public function key(): string
    {
        return 'my-orders';
    }

    public function title(): string
    {
        return 'Siparişlerim';
    }

    public function description(): string
    {
        return 'Dönemdeki siparişleriniz, durumları ve tutarları.';
    }

    public function group(): string
    {
        return 'Sipariş';
    }

    public function audience(): string
    {
        return 'dealer';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters('year') + [
            'status' => ['label' => 'Durum', 'type' => 'select', 'options' => ['' => 'Tümü', 'fulfilled' => 'Onaylanmış (iptal hariç)'] + Order::STATUSES],
        ];
    }

    public function columns(): array
    {
        return [
            'order_no' => ['label' => 'Sipariş no', 'type' => 'text'],
            'created_at' => ['label' => 'Tarih', 'type' => 'datetime'],
            'status_label' => ['label' => 'Durum', 'type' => 'text'],
            'net_total' => ['label' => 'Net', 'type' => 'money', 'total' => true],
            'vat_total' => ['label' => 'KDV', 'type' => 'money', 'total' => true],
            'grand_total' => ['label' => 'Genel toplam', 'type' => 'money', 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        return OrderReport::base($f, $context)
            ->select('orders.order_no', 'orders.created_at', 'orders.net_total', 'orders.vat_total', 'orders.grand_total')
            ->selectRaw(OrderReport::statusCase().' as status_label');
    }
}
