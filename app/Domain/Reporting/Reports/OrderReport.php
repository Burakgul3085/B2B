<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Domain\Support\LocalTime;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/** Siparişler: tarih aralığı, durum ve bayi bazında; tutar toplamları ve ortalama. */
class OrderReport extends Report
{
    public function key(): string
    {
        return 'orders';
    }

    public function title(): string
    {
        return 'Siparişler';
    }

    public function description(): string
    {
        return 'Tarih, durum ve bayiye göre siparişler; ara toplam, indirim, KDV ve genel toplam.';
    }

    public function group(): string
    {
        return 'Sipariş';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters() + [
            'status' => ['label' => 'Durum', 'type' => 'select', 'options' => ['' => 'Tümü', 'pending' => 'Karar bekleyen', 'fulfilled' => 'Onaylanmış (iptal hariç)'] + Order::STATUSES],
            'dealer' => ['label' => 'Bayi', 'type' => 'select', 'options' => Options::dealers()],
        ];
    }

    public function columns(): array
    {
        return [
            'order_no' => ['label' => 'Sipariş no', 'type' => 'text'],
            'created_at' => ['label' => 'Tarih', 'type' => 'datetime'],
            'dealer' => ['label' => 'Bayi', 'type' => 'text'],
            'status_label' => ['label' => 'Durum', 'type' => 'text'],
            'items' => ['label' => 'Kalem', 'type' => 'int', 'default' => false],
            'subtotal' => ['label' => 'Ara toplam', 'type' => 'money', 'total' => true, 'default' => false],
            'discount_total' => ['label' => 'İndirim', 'type' => 'money', 'total' => true, 'default' => false],
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
        return self::base($f, $context)
            ->join('dealer_relationships', 'dealer_relationships.id', '=', 'orders.dealer_relationship_id')
            ->join('dealers', 'dealers.id', '=', 'dealer_relationships.dealer_id')
            ->select('orders.order_no', 'orders.created_at', 'dealers.legal_name as dealer', 'orders.subtotal', 'orders.discount_total',
                'orders.net_total', 'orders.vat_total', 'orders.grand_total')
            ->selectRaw('(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = orders.id) as items')
            ->selectRaw(self::statusCase().' as status_label');
    }

    public static function base(array $f, ReportContext $context): Builder
    {
        return Order::query()
            ->whereBetween('orders.created_at', LocalTime::dayRange($f['from'] ?? LocalTime::now()->startOfMonth()->toDateString(), $f['to'] ?? LocalTime::today()))
            ->when($context->relationshipId, fn ($q, $r) => $q->where('orders.dealer_relationship_id', $r))
            ->when($f['dealer'] ?? null, fn ($q, $d) => $q->where('orders.dealer_relationship_id', $d))
            ->when(($f['status'] ?? null) === 'pending', fn ($q) => $q->whereIn('orders.status', [...Order::PENDING, 'cancel_requested']))
            ->when(($f['status'] ?? null) === 'fulfilled', fn ($q) => $q->whereIn('orders.status', ['approved', 'preparing', 'completed', 'cancel_requested']))
            ->when(isset(Order::STATUSES[$f['status'] ?? '']), fn ($q) => $q->where('orders.status', $f['status']));
    }

    public static function statusCase(): string
    {
        return 'CASE orders.status '.collect(Order::STATUSES)->map(fn ($l, $k) => "WHEN '{$k}' THEN '{$l}'")->implode(' ').' END';
    }
}
