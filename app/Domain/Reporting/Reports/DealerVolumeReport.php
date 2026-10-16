<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\DealerRelationship;
use Illuminate\Database\Eloquent\Builder;

/** Bayi bazında sipariş hacmi, son sipariş tarihi ve cari bakiye. */
class DealerVolumeReport extends Report
{
    public function key(): string
    {
        return 'dealer-volume';
    }

    public function title(): string
    {
        return 'Bayi bazında sipariş hacmi';
    }

    public function description(): string
    {
        return 'Bayilerin dönemdeki onaylanmış sipariş sayısı ve tutarı, son sipariş tarihi ve cari bakiyesi.';
    }

    public function group(): string
    {
        return 'Bayi';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters('year') + [
            'status' => ['label' => 'Bayi durumu', 'type' => 'select', 'options' => ['' => 'Tümü'] + DealerRelationship::STATUSES],
            'group' => ['label' => 'Grup', 'type' => 'select', 'options' => Options::groups()],
        ];
    }

    public function columns(): array
    {
        return [
            'dealer_code' => ['label' => 'Bayi kodu', 'type' => 'text'],
            'dealer' => ['label' => 'Bayi', 'type' => 'text'],
            'status_label' => ['label' => 'Durum', 'type' => 'text'],
            'orders' => ['label' => 'Sipariş', 'type' => 'int', 'total' => true],
            'amount' => ['label' => 'Sipariş tutarı', 'type' => 'money', 'total' => true],
            'last_order_at' => ['label' => 'Son sipariş', 'type' => 'date'],
            'balance' => ['label' => 'Cari bakiye', 'type' => 'money', 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['amount', 'desc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        $from = ($f['from'] ?? now()->startOfYear()->toDateString()).' 00:00:00';
        $to = ($f['to'] ?? now()->toDateString()).' 23:59:59';
        $ok = "('approved','preparing','completed','cancel_requested')";
        $case = 'CASE dealer_relationships.status '.collect(DealerRelationship::STATUSES)->map(fn ($l, $k) => "WHEN '{$k}' THEN '{$l}'")->implode(' ').' END';

        return DealerRelationship::query()
            ->join('dealers', 'dealers.id', '=', 'dealer_relationships.dealer_id')
            ->leftJoin('current_accounts', 'current_accounts.dealer_relationship_id', '=', 'dealer_relationships.id')
            ->select('dealer_relationships.dealer_code', 'dealers.legal_name as dealer')
            ->selectRaw("{$case} as status_label")
            ->selectRaw("(SELECT COUNT(*) FROM orders o WHERE o.dealer_relationship_id = dealer_relationships.id AND o.status IN {$ok} AND o.created_at BETWEEN ? AND ?) as orders", [$from, $to])
            ->selectRaw("(SELECT COALESCE(SUM(o.grand_total), 0) FROM orders o WHERE o.dealer_relationship_id = dealer_relationships.id AND o.status IN {$ok} AND o.created_at BETWEEN ? AND ?) as amount", [$from, $to])
            ->selectRaw('(SELECT MAX(o.created_at) FROM orders o WHERE o.dealer_relationship_id = dealer_relationships.id) as last_order_at')
            ->selectRaw('COALESCE(current_accounts.balance, 0) as balance')
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('dealer_relationships.status', $s))
            ->when($f['group'] ?? null, fn ($q, $g) => $q->where('dealer_relationships.dealer_group_id', $g));
    }
}
