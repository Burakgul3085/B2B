<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;

/** En çok sipariş verilen ürünler (onaylanmış siparişler). */
class TopProductsReport extends Report
{
    public function key(): string
    {
        return 'top-products';
    }

    public function title(): string
    {
        return 'En çok sipariş verilen ürünler';
    }

    public function description(): string
    {
        return 'Onaylanmış siparişlerde ürün bazında miktar, sipariş sayısı ve net tutar.';
    }

    public function group(): string
    {
        return 'Sipariş';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters('year') + [
            'dealer' => ['label' => 'Bayi', 'type' => 'select', 'options' => Options::dealers()],
            'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => Options::categories()],
        ];
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'orders' => ['label' => 'Sipariş sayısı', 'type' => 'int', 'total' => true],
            'dealers' => ['label' => 'Bayi sayısı', 'type' => 'int'],
            'quantity' => ['label' => 'Miktar', 'type' => 'qty', 'total' => true],
            'line_net' => ['label' => 'Net tutar', 'type' => 'money', 'total' => true],
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
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_items.order_id', $orders)
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('products.category_id', Options::categoryIds((int) $c)))
            ->groupBy('order_items.product_id', 'products.sku', 'products.name')
            ->select('products.sku', 'products.name')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders, COUNT(DISTINCT orders.dealer_relationship_id) as dealers')
            ->selectRaw('SUM(order_items.quantity_approved) as quantity, SUM(order_items.line_net) as line_net');
    }
}
