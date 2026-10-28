<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Domain\Support\LocalTime;
use App\Models\CurrentAccountMovement;
use Illuminate\Database\Eloquent\Builder;

/** Cari hareket dökümü (toptancı: tüm bayiler; bayi: kendi ekstresi). */
class AccountMovementReport extends Report
{
    public function key(): string
    {
        return 'account-movements';
    }

    public function title(): string
    {
        return 'Cari hareket dökümü';
    }

    public function description(): string
    {
        return 'Tarih aralığında borç ve alacak hareketleri.';
    }

    public function group(): string
    {
        return 'Cari';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters('year') + [
            'dealer' => ['label' => 'Bayi', 'type' => 'select', 'options' => Options::dealers()],
            'type' => ['label' => 'Hareket', 'type' => 'select', 'options' => ['' => 'Tümü'] + CurrentAccountMovement::TYPES],
        ];
    }

    public function columns(): array
    {
        return [
            'movement_date' => ['label' => 'Tarih', 'type' => 'date'],
            'dealer' => ['label' => 'Bayi', 'type' => 'text'],
            'type_label' => ['label' => 'Hareket', 'type' => 'text'],
            'description' => ['label' => 'Açıklama', 'type' => 'text'],
            'due_date' => ['label' => 'Vade', 'type' => 'date', 'default' => false],
            'debit' => ['label' => 'Borç', 'type' => 'money', 'total' => true],
            'credit' => ['label' => 'Alacak', 'type' => 'money', 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['movement_date', 'asc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        $case = 'CASE current_account_movements.type '.collect(CurrentAccountMovement::TYPES)->map(fn ($l, $k) => "WHEN '{$k}' THEN '{$l}'")->implode(' ').' END';

        return CurrentAccountMovement::query()
            ->join('current_accounts', 'current_accounts.id', '=', 'current_account_movements.current_account_id')
            ->join('dealer_relationships', 'dealer_relationships.id', '=', 'current_accounts.dealer_relationship_id')
            ->join('dealers', 'dealers.id', '=', 'dealer_relationships.dealer_id')
            ->select('current_account_movements.movement_date', 'dealers.legal_name as dealer', 'current_account_movements.description',
                'current_account_movements.due_date', 'current_account_movements.debit', 'current_account_movements.credit')
            ->selectRaw("{$case} as type_label")
            ->whereBetween('current_account_movements.movement_date', [$f['from'] ?? LocalTime::now()->startOfYear()->toDateString(), $f['to'] ?? LocalTime::today()])
            ->when($context->relationshipId, fn ($q, $r) => $q->where('current_accounts.dealer_relationship_id', $r))
            ->when($f['dealer'] ?? null, fn ($q, $d) => $q->where('current_accounts.dealer_relationship_id', $d))
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('current_account_movements.type', $t));
    }
}
