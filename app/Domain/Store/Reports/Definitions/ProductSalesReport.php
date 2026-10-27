<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use App\Models\Store\StoreOrderItem;
use Illuminate\Database\Eloquent\Builder;

/** Ürün satışları: ürün (seçenek) bazında satılan adet, sipariş sayısı ve net ciro. */
class ProductSalesReport extends StoreReport
{
    public function key(): string
    {
        return 'urun-satislari';
    }

    public function title(): string
    {
        return 'Ürün satışları';
    }

    public function description(): string
    {
        return 'En çok satan ürünler: satılan adet, sipariş sayısı, net ciro ve ortalama satış fiyatı.';
    }

    public function group(): string
    {
        return 'Ürün';
    }

    public function icon(): string
    {
        return 'package';
    }

    public function filters(): array
    {
        return ReportOptions::dates() + [
            'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => ReportOptions::categories()],
            'brand' => ['label' => 'Marka', 'type' => 'select', 'options' => ReportOptions::brands()],
            'q' => ['label' => 'Ara (ürün adı, ürün kodu)', 'type' => 'text', 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'option_label' => ['label' => 'Seçenek', 'type' => 'text', 'default' => false],
            'category' => ['label' => 'Kategori', 'type' => 'text'],
            'brand' => ['label' => 'Marka', 'type' => 'text', 'default' => false],
            'orders' => ['label' => 'Sipariş', 'type' => 'int', 'total' => true],
            'quantity' => ['label' => 'Satılan adet', 'type' => 'qty', 'total' => true],
            'revenue' => ['label' => 'Net ciro', 'type' => 'money', 'total' => true],
            'average_price' => ['label' => 'Ortalama satış fiyatı', 'type' => 'money'],
            'stock' => ['label' => 'Güncel stok', 'type' => 'qty', 'default' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['quantity', 'desc'];
    }

    public function query(array $f): Builder
    {
        return self::sold($f)
            ->leftJoin('store_product_variants as v', 'v.id', '=', 'store_order_items.variant_id')
            ->groupBy('store_order_items.sku')
            ->selectRaw('store_order_items.sku, MAX(store_order_items.product_name) as name, MAX(store_order_items.option_label) as option_label')
            ->selectRaw('MAX(c.name) as category, MAX(b.name) as brand, MAX(v.stock_quantity) as stock')
            ->selectRaw('COUNT(DISTINCT store_order_items.order_id) as orders, SUM(store_order_items.quantity) as quantity')
            ->selectRaw('SUM(store_order_items.line_total - store_order_items.discount_amount) as revenue')
            ->selectRaw('SUM(store_order_items.line_total - store_order_items.discount_amount) / NULLIF(SUM(store_order_items.quantity), 0) as average_price');
    }

    /** Satış sayılan siparişlerin kalemleri, ürün, kategori ve markayla (ortak). */
    public static function sold(array $f): Builder
    {
        $orders = OrdersReport::base(['status' => 'sales'] + $f)->select('store_orders.id');

        return StoreOrderItem::query()
            ->whereIn('store_order_items.order_id', $orders)
            ->leftJoin('store_products as p', 'p.id', '=', 'store_order_items.product_id')
            ->leftJoin('store_categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('store_brands as b', 'b.id', '=', 'p.brand_id')
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('p.category_id', ReportOptions::categoryIds((int) $c)))
            ->when($f['brand'] ?? null, fn ($q, $b) => $q->where('p.brand_id', $b))
            ->when(trim((string) ($f['q'] ?? '')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('store_order_items.product_name', 'like', $like)->orWhere('store_order_items.sku', 'like', $like));
            });
    }
}
