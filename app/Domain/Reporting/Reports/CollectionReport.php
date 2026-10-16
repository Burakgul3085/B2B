<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\CollectionEntry;
use Illuminate\Database\Eloquent\Builder;

/** Tahsilatlar. */
class CollectionReport extends Report
{
    public function key(): string
    {
        return 'collections';
    }

    public function title(): string
    {
        return 'Tahsilatlar';
    }

    public function description(): string
    {
        return 'Yönteme ve bayiye göre tahsilatlar (iptal edilenler ayrıca işaretlenir).';
    }

    public function group(): string
    {
        return 'Cari';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters() + [
            'method' => ['label' => 'Yöntem', 'type' => 'select', 'options' => ['' => 'Tümü'] + CollectionEntry::METHODS],
            'dealer' => ['label' => 'Bayi', 'type' => 'select', 'options' => Options::dealers()],
            'cancelled' => ['label' => 'İptaller', 'type' => 'select', 'options' => ['0' => 'Hariç', '1' => 'Dahil'], 'default' => '0', 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'collected_on' => ['label' => 'Tarih', 'type' => 'date'],
            'dealer' => ['label' => 'Bayi', 'type' => 'text'],
            'method_label' => ['label' => 'Yöntem', 'type' => 'text'],
            'amount' => ['label' => 'Tutar', 'type' => 'money', 'total' => true],
            'order_no' => ['label' => 'Sipariş', 'type' => 'text', 'default' => false],
            'description' => ['label' => 'Açıklama', 'type' => 'text'],
            'state' => ['label' => 'Durum', 'type' => 'text', 'default' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['collected_on', 'desc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        $case = 'CASE collections.method '.collect(CollectionEntry::METHODS)->map(fn ($l, $k) => "WHEN '{$k}' THEN '{$l}'")->implode(' ').' END';

        return CollectionEntry::query()
            ->join('current_accounts', 'current_accounts.id', '=', 'collections.current_account_id')
            ->join('dealer_relationships', 'dealer_relationships.id', '=', 'current_accounts.dealer_relationship_id')
            ->join('dealers', 'dealers.id', '=', 'dealer_relationships.dealer_id')
            ->leftJoin('orders', 'orders.id', '=', 'collections.order_id')
            ->select('collections.collected_on', 'dealers.legal_name as dealer', 'collections.amount', 'orders.order_no', 'collections.description')
            ->selectRaw("{$case} as method_label")
            ->selectRaw("CASE WHEN collections.cancelled_at IS NULL THEN 'Geçerli' ELSE 'İptal' END as state")
            ->whereBetween('collections.collected_on', [$f['from'] ?? now()->startOfMonth()->toDateString(), $f['to'] ?? now()->toDateString()])
            ->when($f['method'] ?? null, fn ($q, $m) => $q->where('collections.method', $m))
            ->when($f['dealer'] ?? null, fn ($q, $d) => $q->where('current_accounts.dealer_relationship_id', $d))
            ->when(($f['cancelled'] ?? '0') !== '1', fn ($q) => $q->whereNull('collections.cancelled_at'));
    }
}
