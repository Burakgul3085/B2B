<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Ledger\AccountAnalysis;
use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\CurrentAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Cari bakiye listesi ve vade yaşlandırma. */
class AccountBalanceReport extends Report
{
    public function key(): string
    {
        return 'account-balances';
    }

    public function title(): string
    {
        return 'Cari bakiye ve vadesi geçmiş alacaklar';
    }

    public function description(): string
    {
        return 'Bayi bazında bakiye; vadesi geçmiş, 7 gün içinde gelecek ve gelmemiş tutarlar (FIFO).';
    }

    public function group(): string
    {
        return 'Cari';
    }

    public function filters(ReportContext $context): array
    {
        return [
            'view' => ['label' => 'Göster', 'type' => 'select', 'options' => ['debt' => 'Borcu olanlar', 'all' => 'Tümü'], 'default' => 'debt'],
        ];
    }

    public function columns(): array
    {
        return [
            'code' => ['label' => 'Cari kodu', 'type' => 'text'],
            'dealer' => ['label' => 'Bayi', 'type' => 'text'],
            'balance' => ['label' => 'Bakiye', 'type' => 'money', 'total' => true],
            'overdue' => ['label' => 'Vadesi geçmiş', 'type' => 'money', 'total' => true, 'sortable' => false],
            'due_soon' => ['label' => '7 gün içinde', 'type' => 'money', 'total' => true, 'sortable' => false],
            'not_due' => ['label' => 'Vadesi gelmemiş', 'type' => 'money', 'total' => true, 'sortable' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['balance', 'desc'];
    }

    public function totalsFromRows(): bool
    {
        return true;
    }

    public function query(array $f, ReportContext $context): Builder
    {
        return CurrentAccount::query()
            ->join('dealer_relationships', 'dealer_relationships.id', '=', 'current_accounts.dealer_relationship_id')
            ->join('dealers', 'dealers.id', '=', 'dealer_relationships.dealer_id')
            ->select('current_accounts.id', 'current_accounts.code', 'dealers.legal_name as dealer', 'current_accounts.balance')
            ->when(($f['view'] ?? 'debt') === 'debt', fn ($q) => $q->where('current_accounts.balance', '>', 0));
    }

    public function transform(Collection $rows, array $filters, ReportContext $context): Collection
    {
        $accounts = CurrentAccount::whereIn('id', $rows->pluck('id'))->get();
        $aging = app(AccountAnalysis::class)->agingMany($accounts);

        return $rows->map(function ($row) use ($aging) {
            $a = $aging[$row->id] ?? ['overdue' => '0.00', 'due_soon' => '0.00', 'not_due' => '0.00'];
            $row->overdue = $a['overdue'];
            $row->due_soon = $a['due_soon'];
            $row->not_due = $a['not_due'];

            return $row;
        });
    }
}
