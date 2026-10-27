<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use App\Models\Store\StoreOrderItem;
use Illuminate\Database\Eloquent\Builder;

/** Sipariş kalemleri: her siparişteki ürün satırları, indirim payı ve KDV ile. */
class OrderItemsReport extends StoreReport
{
    public function key(): string
    {
        return 'siparis-kalemleri';
    }

    public function title(): string
    {
        return 'Sipariş kalemleri';
    }

    public function description(): string
    {
        return 'Siparişlerdeki ürün satırları: adet, birim fiyat, indirim payı, net tutar ve KDV.';
    }

    public function group(): string
    {
        return 'Satış';
    }

    public function icon(): string
    {
        return 'list';
    }

    public function filters(): array
    {
        return ReportOptions::dates() + [
            'status' => ReportOptions::orderStatus(),
            'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => ReportOptions::categories()],
            'brand' => ['label' => 'Marka', 'type' => 'select', 'options' => ReportOptions::brands(), 'advanced' => true],
            'q' => ['label' => 'Ara (ürün adı, ürün kodu, sipariş no)', 'type' => 'text', 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'order_no' => ['label' => 'Sipariş no', 'type' => 'text'],
            'created_at' => ['label' => 'Tarih', 'type' => 'datetime'],
            'customer' => ['label' => 'Müşteri', 'type' => 'text', 'default' => false],
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'product_name' => ['label' => 'Ürün', 'type' => 'text'],
            'option_label' => ['label' => 'Seçenek', 'type' => 'text', 'default' => false],
            'quantity' => ['label' => 'Adet', 'type' => 'qty', 'total' => true],
            'unit_price' => ['label' => 'Birim fiyat', 'type' => 'money'],
            'line_total' => ['label' => 'Tutar', 'type' => 'money', 'total' => true],
            'discount_amount' => ['label' => 'İndirim payı', 'type' => 'money', 'total' => true],
            'net' => ['label' => 'Net tutar', 'type' => 'money', 'total' => true],
            'vat_rate' => ['label' => 'KDV oranı', 'type' => 'percent', 'default' => false],
            'vat_amount' => ['label' => 'KDV', 'type' => 'money', 'total' => true, 'default' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(array $f): Builder
    {
        $orders = OrdersReport::base($f)->select('store_orders.id');

        return StoreOrderItem::query()
            ->join('store_orders', 'store_orders.id', '=', 'store_order_items.order_id')
            ->leftJoin('store_products', 'store_products.id', '=', 'store_order_items.product_id')
            ->whereIn('store_order_items.order_id', $orders)
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('store_products.category_id', ReportOptions::categoryIds((int) $c)))
            ->when($f['brand'] ?? null, fn ($q, $b) => $q->where('store_products.brand_id', $b))
            ->when(trim((string) ($f['q'] ?? '')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('store_order_items.product_name', 'like', $like)->orWhere('store_order_items.sku', 'like', $like)
                    ->orWhere('store_orders.order_no', 'like', $like));
            })
            ->select('store_orders.order_no', 'store_orders.created_at', 'store_orders.shipping_name as customer', 'store_order_items.sku',
                'store_order_items.product_name', 'store_order_items.option_label', 'store_order_items.quantity', 'store_order_items.unit_price',
                'store_order_items.line_total', 'store_order_items.discount_amount', 'store_order_items.vat_rate', 'store_order_items.vat_amount')
            ->selectRaw('(store_order_items.line_total - store_order_items.discount_amount) as net');
    }
}
