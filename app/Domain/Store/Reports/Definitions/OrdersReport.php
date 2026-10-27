<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use App\Models\Store\StoreOrder;
use Illuminate\Database\Eloquent\Builder;

/** Siparişler: tarih, durum, ödeme, teslimat, il ve kargo firmasına göre; tutar toplamları. */
class OrdersReport extends StoreReport
{
    public function key(): string
    {
        return 'siparisler';
    }

    public function title(): string
    {
        return 'Siparişler';
    }

    public function description(): string
    {
        return 'Sipariş listesi: müşteri, durum, ödeme ve teslimat; ürün, indirim, teslimat, iade ve net tutarlar.';
    }

    public function group(): string
    {
        return 'Satış';
    }

    public function icon(): string
    {
        return 'file';
    }

    public function filters(): array
    {
        return ReportOptions::dates() + [
            'status' => ReportOptions::orderStatus(),
            'payment' => ReportOptions::payment(),
            'delivery' => ReportOptions::delivery(),
            'province' => ['label' => 'İl', 'type' => 'select', 'options' => ReportOptions::provinces(), 'advanced' => true],
            'carrier' => ['label' => 'Kargo firması', 'type' => 'select', 'options' => ReportOptions::carriers(), 'advanced' => true],
            'discount' => ['label' => 'İndirim', 'type' => 'select', 'options' => ['' => 'Tümü', 'yes' => 'İndirimli siparişler', 'no' => 'İndirimsiz siparişler'], 'advanced' => true],
            'q' => ['label' => 'Ara (sipariş no, müşteri, e-posta, telefon)', 'type' => 'text', 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'order_no' => ['label' => 'Sipariş no', 'type' => 'text'],
            'created_at' => ['label' => 'Tarih', 'type' => 'datetime'],
            'customer' => ['label' => 'Müşteri', 'type' => 'text'],
            'email' => ['label' => 'E-posta', 'type' => 'text', 'default' => false],
            'phone' => ['label' => 'Telefon', 'type' => 'text', 'default' => false],
            'province' => ['label' => 'İl', 'type' => 'text'],
            'district' => ['label' => 'İlçe', 'type' => 'text', 'default' => false],
            'status_label' => ['label' => 'Durum', 'type' => 'text'],
            'payment_label' => ['label' => 'Ödeme', 'type' => 'text'],
            'delivery_label' => ['label' => 'Teslimat', 'type' => 'text', 'default' => false],
            'carrier' => ['label' => 'Kargo firması', 'type' => 'text', 'default' => false],
            'tracking_number' => ['label' => 'Takip no', 'type' => 'text', 'default' => false],
            'items' => ['label' => 'Ürün adedi', 'type' => 'int', 'total' => true, 'default' => false],
            'subtotal' => ['label' => 'Ürünler', 'type' => 'money', 'total' => true],
            'discount_total' => ['label' => 'İndirim', 'type' => 'money', 'total' => true],
            'fees' => ['label' => 'Teslimat ve hizmet', 'type' => 'money', 'total' => true],
            'total' => ['label' => 'Sipariş tutarı', 'type' => 'money', 'total' => true],
            'refunded_total' => ['label' => 'İade', 'type' => 'money', 'total' => true, 'default' => false],
            'net' => ['label' => 'Net', 'type' => 'money', 'total' => true],
            'discount_name' => ['label' => 'Kampanya / kupon', 'type' => 'text', 'default' => false],
            'invoice_no' => ['label' => 'Fatura no', 'type' => 'text', 'default' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(array $f): Builder
    {
        return self::base($f)
            ->leftJoin('store_invoices', 'store_invoices.order_id', '=', 'store_orders.id')
            ->select('store_orders.order_no', 'store_orders.created_at', 'store_orders.shipping_name as customer', 'store_orders.email', 'store_orders.phone',
                'store_orders.shipping_province as province', 'store_orders.shipping_district as district', 'store_orders.carrier', 'store_orders.tracking_number',
                'store_orders.subtotal', 'store_orders.discount_total', 'store_orders.total', 'store_orders.refunded_total', 'store_orders.discount_name',
                'store_invoices.invoice_no')
            ->selectRaw('(store_orders.shipping_fee + store_orders.cod_fee) as fees, (store_orders.total - store_orders.refunded_total) as net')
            ->selectRaw('(SELECT COALESCE(SUM(i.quantity), 0) FROM store_order_items i WHERE i.order_id = store_orders.id) as items')
            ->selectRaw(ReportOptions::label('store_orders.status', StoreOrder::STATUSES).' as status_label')
            ->selectRaw(ReportOptions::label('store_orders.payment_method', StoreOrder::PAYMENT_METHODS).' as payment_label')
            ->selectRaw(ReportOptions::label('store_orders.delivery_method', StoreOrder::DELIVERY_METHODS).' as delivery_label');
    }

    /** Sipariş filtreleri (diğer raporlar da kullanır). */
    public static function base(array $f): Builder
    {
        $query = StoreOrder::query();
        if ($bounds = ReportOptions::bounds($f)) {
            $query->whereBetween('store_orders.created_at', $bounds);
        }
        ReportOptions::applyStatus($query, $f['status'] ?? null);

        return $query
            ->when($f['payment'] ?? null, fn ($q, $v) => $q->where('store_orders.payment_method', $v))
            ->when($f['delivery'] ?? null, fn ($q, $v) => $q->where('store_orders.delivery_method', $v))
            ->when($f['province'] ?? null, fn ($q, $v) => $q->where('store_orders.shipping_province_id', $v))
            ->when($f['carrier'] ?? null, fn ($q, $v) => $q->where('store_orders.carrier_id', $v))
            ->when(($f['discount'] ?? null) === 'yes', fn ($q) => $q->where('store_orders.discount_total', '>', 0))
            ->when(($f['discount'] ?? null) === 'no', fn ($q) => $q->where('store_orders.discount_total', 0))
            ->when(trim((string) ($f['q'] ?? '')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $digits = preg_replace('/\D/', '', $term);
                $q->where(function ($w) use ($like, $digits) {
                    $w->where('store_orders.order_no', 'like', $like)->orWhere('store_orders.shipping_name', 'like', $like)->orWhere('store_orders.email', 'like', $like);
                    if ($digits !== '') {
                        $w->orWhere('store_orders.phone', 'like', '%'.$digits.'%');
                    }
                });
            });
    }
}
