<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use App\Models\Store\StoreReturn;
use Illuminate\Database\Eloquent\Builder;

/** İadeler: talepler, nedenleri, durumları ve iade edilen tutar. */
class ReturnsReport extends StoreReport
{
    public function key(): string
    {
        return 'iadeler';
    }

    public function title(): string
    {
        return 'İadeler';
    }

    public function description(): string
    {
        return 'İade talepleri: sipariş, müşteri, neden, durum, iade edilen ürün adedi ve iade tutarı.';
    }

    public function group(): string
    {
        return 'İade ve kargo';
    }

    public function icon(): string
    {
        return 'refresh';
    }

    public function filters(): array
    {
        return ReportOptions::dates('year') + [
            'status' => ['label' => 'Durum', 'type' => 'select', 'options' => ['' => 'Tümü', 'open' => 'Açık talepler'] + StoreReturn::STATUSES],
            'reason' => ['label' => 'Neden', 'type' => 'select', 'options' => ['' => 'Tümü'] + StoreReturn::REASONS],
        ];
    }

    public function columns(): array
    {
        return [
            'return_no' => ['label' => 'İade no', 'type' => 'text'],
            'created_at' => ['label' => 'Talep tarihi', 'type' => 'datetime'],
            'order_no' => ['label' => 'Sipariş no', 'type' => 'text'],
            'customer' => ['label' => 'Müşteri', 'type' => 'text'],
            'reason_label' => ['label' => 'Neden', 'type' => 'text'],
            'status_label' => ['label' => 'Durum', 'type' => 'text'],
            'items' => ['label' => 'Ürün adedi', 'type' => 'int', 'total' => true],
            'refund_amount' => ['label' => 'İade tutarı', 'type' => 'money', 'total' => true],
            'refunded_at' => ['label' => 'Para iadesi', 'type' => 'datetime', 'default' => false],
            'customer_note' => ['label' => 'Müşteri açıklaması', 'type' => 'text', 'default' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(array $f): Builder
    {
        $query = StoreReturn::query()->join('store_orders', 'store_orders.id', '=', 'store_returns.order_id');
        if ($bounds = ReportOptions::bounds($f)) {
            $query->whereBetween('store_returns.created_at', $bounds);
        }

        return $query
            ->when(($f['status'] ?? null) === 'open', fn ($q) => $q->whereIn('store_returns.status', StoreReturn::OPEN))
            ->when(isset(StoreReturn::STATUSES[$f['status'] ?? '']), fn ($q) => $q->where('store_returns.status', $f['status']))
            ->when($f['reason'] ?? null, fn ($q, $r) => $q->where('store_returns.reason', $r))
            ->select('store_returns.return_no', 'store_returns.created_at', 'store_orders.order_no', 'store_orders.shipping_name as customer',
                'store_returns.refund_amount', 'store_returns.refunded_at', 'store_returns.customer_note')
            ->selectRaw('(SELECT COALESCE(SUM(ri.quantity), 0) FROM store_return_items ri WHERE ri.return_id = store_returns.id) as items')
            ->selectRaw(ReportOptions::label('store_returns.reason', StoreReturn::REASONS).' as reason_label')
            ->selectRaw(ReportOptions::label('store_returns.status', StoreReturn::STATUSES).' as status_label');
    }
}
