<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;

/** Sipariş kalemleri: ürün ve bayi bazında miktar ve tutar. */
class OrderItemReport extends Report
{
    public function key(): string
    {
        return 'order-items';
    }

    public function title(): string
    {
        return 'Sipariş kalemleri';
    }

    public function description(): string
    {
        return 'Kalem bazında istenen ve onaylanan miktar, birim fiyat, indirim ve tutarlar.';
    }

    public function group(): string
    {
        return 'Sipariş';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters() + [
            'status' => ['label' => 'Durum', 'type' => 'select', 'options' => ['fulfilled' => 'Onaylanmış (iptal hariç)', '' => 'Tümü', 'pending' => 'Karar bekleyen'], 'default' => 'fulfilled'],
            'dealer' => ['label' => 'Bayi', 'type' => 'select', 'options' => Options::dealers()],
            'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => Options::categories(), 'advanced' => true],
            'q' => ['label' => 'Ürün', 'type' => 'text', 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'order_no' => ['label' => 'Sipariş no', 'type' => 'text'],
            'created_at' => ['label' => 'Tarih', 'type' => 'date'],
            'dealer' => ['label' => 'Bayi', 'type' => 'text'],
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'quantity_requested' => ['label' => 'İstenen', 'type' => 'qty', 'total' => true, 'default' => false],
            'quantity' => ['label' => 'Miktar', 'type' => 'qty', 'total' => true],
            'unit_price' => ['label' => 'Birim fiyat', 'type' => 'money'],
            'discount_percent' => ['label' => 'İndirim', 'type' => 'percent', 'default' => false],
            'line_net' => ['label' => 'Net', 'type' => 'money', 'total' => true],
            'line_vat' => ['label' => 'KDV', 'type' => 'money', 'total' => true, 'default' => false],
            'line_total' => ['label' => 'Toplam', 'type' => 'money', 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        $orders = OrderReport::base($f, $context)->select('orders.id');

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('dealer_relationships', 'dealer_relationships.id', '=', 'orders.dealer_relationship_id')
            ->join('dealers', 'dealers.id', '=', 'dealer_relationships.dealer_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_items.order_id', $orders)
            ->select('orders.order_no', 'orders.created_at', 'dealers.legal_name as dealer', 'order_items.sku', 'order_items.name',
                'order_items.quantity_requested', 'order_items.unit_price', 'order_items.discount_percent',
                'order_items.line_net', 'order_items.line_vat', 'order_items.line_total')
            ->selectRaw('COALESCE(order_items.quantity_approved, order_items.quantity_requested) as quantity')
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('products.category_id', Options::categoryIds((int) $c)))
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q->where('order_items.name', 'like', Options::like($t))->orWhere('order_items.sku', 'like', Options::like($t))));
    }
}
