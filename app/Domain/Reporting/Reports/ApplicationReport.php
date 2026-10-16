<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\DealerApplication;
use Illuminate\Database\Eloquent\Builder;

/** Bayilik başvuruları. */
class ApplicationReport extends Report
{
    public function key(): string
    {
        return 'applications';
    }

    public function title(): string
    {
        return 'Bayilik başvuruları';
    }

    public function description(): string
    {
        return 'Dönemdeki başvurular ve sonuçları.';
    }

    public function group(): string
    {
        return 'Bayi';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters('year') + [
            'status' => ['label' => 'Durum', 'type' => 'select', 'options' => ['' => 'Tümü'] + DealerApplication::STATUSES],
        ];
    }

    public function columns(): array
    {
        return [
            'created_at' => ['label' => 'Tarih', 'type' => 'date'],
            'legal_name' => ['label' => 'İşletme', 'type' => 'text'],
            'contact_name' => ['label' => 'Yetkili', 'type' => 'text'],
            'city' => ['label' => 'İl', 'type' => 'text'],
            'status_label' => ['label' => 'Durum', 'type' => 'text'],
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        $case = 'CASE status '.collect(DealerApplication::STATUSES)->map(fn ($l, $k) => "WHEN '{$k}' THEN '{$l}'")->implode(' ').' END';

        return DealerApplication::query()
            ->select('created_at', 'legal_name', 'contact_name', 'city')->selectRaw("{$case} as status_label")
            ->whereBetween('created_at', [($f['from'] ?? now()->startOfYear()->toDateString()).' 00:00:00', ($f['to'] ?? now()->toDateString()).' 23:59:59'])
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s));
    }
}
