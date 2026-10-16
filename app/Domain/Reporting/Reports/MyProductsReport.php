<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;

/** Bayi: ürün bazında satın alımlar. */
class MyProductsReport extends Report
{
    public function key(): string
    {
        return 'my-products';
    }

    public function title(): string
    {
        return 'Ürün bazında alımlarım';
    }

    public function description(): string
    {
        return 'Onaylanmış siparişlerinizde ürün bazında miktar ve tutar.';
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
        return Options::dateFilters('year');
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'quantity' => ['label' => 'Miktar', 'type' => 'qty', 'total' => true],
            'line_total' => ['label' => 'Tutar (KDV dahil)', 'type' => 'money', 'total' => true],
            'last_order_at' => ['label' => 'Son sipariş', 'type' => 'date'],
        ];
    }

    public function defaultSort(): array
    {
        return ['quantity', 'desc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        $orders = OrderReport::base(['status' => 'fulfilled'] + $f, $context)->select('orders.id');

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.order_id', $orders)
            ->groupBy('order_items.product_id', 'order_items.sku')
            ->select('order_items.sku')
            ->selectRaw('MAX(order_items.name) as name, SUM(order_items.quantity_approved) as quantity, SUM(order_items.line_total) as line_total, MAX(orders.created_at) as last_order_at');
    }
}
