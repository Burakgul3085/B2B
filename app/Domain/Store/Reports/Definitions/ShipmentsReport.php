<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use App\Models\Store\StoreOrder;
use Illuminate\Database\Eloquent\Builder;

/** Kargo gönderileri: kargoya verilen siparişler, firma, takip no ve hazırlama süresi. */
class ShipmentsReport extends StoreReport
{
    public function key(): string
    {
        return 'kargo-gonderileri';
    }

    public function title(): string
    {
        return 'Kargo gönderileri';
    }

    public function description(): string
    {
        return 'Kargoya verilen ve yola çıkan siparişler: firma, takip numarası, il, hazırlama ve teslim süresi, teslimat ücreti.';
    }

    public function group(): string
    {
        return 'İade ve kargo';
    }

    public function icon(): string
    {
        return 'truck';
    }

    public function filters(): array
    {
        return ReportOptions::dates('month', 'Gönderim') + [
            'carrier' => ['label' => 'Kargo firması', 'type' => 'select', 'options' => ReportOptions::carriers()],
            'delivery' => ReportOptions::delivery(),
            'state' => ['label' => 'Durum', 'type' => 'select', 'options' => ['' => 'Tümü', 'shipped' => 'Yolda', 'delivered' => 'Teslim edildi', 'not_delivered' => 'Teslim alınmadı']],
        ];
    }

    public function columns(): array
    {
        return [
            'order_no' => ['label' => 'Sipariş no', 'type' => 'text'],
            'shipped_at' => ['label' => 'Gönderim', 'type' => 'datetime'],
            'carrier' => ['label' => 'Kargo firması', 'type' => 'text'],
            'tracking_number' => ['label' => 'Takip no', 'type' => 'text'],
            'customer' => ['label' => 'Müşteri', 'type' => 'text', 'default' => false],
            'province' => ['label' => 'İl', 'type' => 'text'],
            'status_label' => ['label' => 'Durum', 'type' => 'text'],
            'prep_days' => ['label' => 'Hazırlama (gün)', 'type' => 'int'],
            'delivered_at' => ['label' => 'Teslim', 'type' => 'datetime', 'default' => false],
            'transit_days' => ['label' => 'Yolda (gün)', 'type' => 'int', 'default' => false],
            'shipping_fee' => ['label' => 'Teslimat ücreti', 'type' => 'money', 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['shipped_at', 'desc'];
    }

    public function query(array $f): Builder
    {
        $query = StoreOrder::query()->whereNotNull('store_orders.shipped_at');
        if ($bounds = ReportOptions::bounds($f)) {
            $query->whereBetween('store_orders.shipped_at', $bounds);
        }

        return $query
            ->when($f['carrier'] ?? null, fn ($q, $v) => $q->where('store_orders.carrier_id', $v))
            ->when($f['delivery'] ?? null, fn ($q, $v) => $q->where('store_orders.delivery_method', $v))
            ->when($f['state'] ?? null, fn ($q, $v) => $q->where('store_orders.status', $v))
            ->select('store_orders.order_no', 'store_orders.shipped_at', 'store_orders.tracking_number', 'store_orders.shipping_name as customer',
                'store_orders.shipping_province as province', 'store_orders.delivered_at', 'store_orders.shipping_fee')
            ->selectRaw("COALESCE(store_orders.carrier, CASE WHEN store_orders.delivery_method = 'local' THEN 'Mağaza teslimatı' END) as carrier")
            ->selectRaw('DATEDIFF(store_orders.shipped_at, COALESCE(store_orders.paid_at, store_orders.approved_at, store_orders.created_at)) as prep_days')
            ->selectRaw('DATEDIFF(store_orders.delivered_at, store_orders.shipped_at) as transit_days')
            ->selectRaw(ReportOptions::label('store_orders.status', StoreOrder::STATUSES).' as status_label');
    }
}
