<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use Illuminate\Database\Eloquent\Builder;

/** Kategori ve marka satışları: hangi kategori ya da markanın ne kadar sattığı ve cirodaki payı. */
class CategoryBrandSalesReport extends StoreReport
{
    public function key(): string
    {
        return 'kategori-marka-satislari';
    }

    public function title(): string
    {
        return 'Kategori ve marka satışları';
    }

    public function description(): string
    {
        return 'Kategori veya marka bazında satılan ürün çeşidi, adet, net ciro ve cirodaki pay.';
    }

    public function group(): string
    {
        return 'Ürün';
    }

    public function icon(): string
    {
        return 'grid';
    }

    public function filters(): array
    {
        return ['by' => ['label' => 'Gruplama', 'type' => 'select', 'default' => 'category', 'options' => ['category' => 'Kategoriye göre', 'brand' => 'Markaya göre']]]
            + ReportOptions::dates();
    }

    public function columns(): array
    {
        return [
            'name' => ['label' => 'Kategori / marka', 'type' => 'text'],
            'products' => ['label' => 'Satılan ürün çeşidi', 'type' => 'int'],
            'orders' => ['label' => 'Sipariş', 'type' => 'int'],
            'quantity' => ['label' => 'Satılan adet', 'type' => 'qty', 'total' => true],
            'revenue' => ['label' => 'Net ciro', 'type' => 'money', 'total' => true],
            'share' => ['label' => 'Cirodaki pay', 'type' => 'percent'],
        ];
    }

    public function defaultSort(): array
    {
        return ['revenue', 'desc'];
    }

    public function query(array $f): Builder
    {
        $name = ($f['by'] ?? 'category') === 'brand' ? "COALESCE(b.name, 'Markasız')" : "COALESCE(c.name, 'Kategorisiz')";

        return ProductSalesReport::sold($f)
            ->groupBy('name')
            ->selectRaw("{$name} as name, COUNT(DISTINCT store_order_items.sku) as products, COUNT(DISTINCT store_order_items.order_id) as orders")
            ->selectRaw('SUM(store_order_items.quantity) as quantity, SUM(store_order_items.line_total - store_order_items.discount_amount) as revenue')
            ->selectRaw('100 * SUM(store_order_items.line_total - store_order_items.discount_amount) / NULLIF(SUM(SUM(store_order_items.line_total - store_order_items.discount_amount)) OVER (), 0) as share');
    }
}
