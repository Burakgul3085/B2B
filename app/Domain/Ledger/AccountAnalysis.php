<?php

namespace App\Domain\Ledger;

use App\Models\CurrentAccount;
use App\Models\CurrentAccountMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vade yaşlandırma ve ekstre (docs/03-is-kurallari.md §6, C9). Hiçbir şey saklanmaz; her şey hareketlerden türetilir.
 *
 * FIFO eşleştirme: alacaklar (tahsilat, iade, alacak düzeltmesi, açılıştaki alacak) en eski vadeli borçtan başlayarak
 * kapatılır. Birbirini ters çeviren hareket çiftleri (iptal edilen sipariş/tahsilat) hesaba katılmaz.
 */
class AccountAnalysis
{
    public const DUE_SOON_DAYS = 7;

    /**
     * @return array{overdue: string, due_soon: string, not_due: string, open: string, credit: string, items: Collection}
     */
    public function aging(CurrentAccount $account, ?string $asOf = null): array
    {
        return $this->agingFromMovements(CurrentAccountMovement::where('current_account_id', $account->id)->orderBy('id')->get(), $asOf);
    }

    /** Birden çok hesap için tek sorguda yaşlandırma (cari listesi). @return array<int, array> hesap ID'sine göre */
    public function agingMany(Collection $accounts, ?string $asOf = null): array
    {
        $movements = CurrentAccountMovement::whereIn('current_account_id', $accounts->modelKeys())->orderBy('id')->get()->groupBy('current_account_id');

        return $accounts->mapWithKeys(fn ($a) => [$a->id => $this->agingFromMovements($movements->get($a->id, collect()), $asOf)])->all();
    }

    public function agingFromMovements(Collection $movements, ?string $asOf = null): array
    {
        $today = $asOf ?? now()->toDateString();
        $soon = Carbon::parse($today)->addDays(self::DUE_SOON_DAYS)->toDateString();

        // Ters kayıt çiftlerini dışla.
        $reversed = $movements->pluck('reverses_movement_id')->filter()->flip();
        $effective = $movements->reject(fn ($m) => $m->reverses_movement_id !== null || $reversed->has($m->id));

        $debits = $effective->filter(fn ($m) => bccomp((string) $m->debit, '0', 2) > 0)
            ->sortBy(fn ($m) => ($m->due_date ?? $m->movement_date)->toDateString().'-'.str_pad((string) $m->id, 12, '0', STR_PAD_LEFT))
            ->values();
        $credit = $effective->reduce(fn ($c, $m) => bcadd($c, (string) $m->credit, 2), '0.00');

        $remainingCredit = $credit;
        $totals = ['overdue' => '0.00', 'due_soon' => '0.00', 'not_due' => '0.00'];
        $items = collect();

        foreach ($debits as $m) {
            $amount = (string) $m->debit;
            $applied = bccomp($remainingCredit, $amount, 2) >= 0 ? $amount : $remainingCredit;
            $remainingCredit = bcsub($remainingCredit, $applied, 2);
            $open = bcsub($amount, $applied, 2);

            if (bccomp($open, '0', 2) <= 0) {
                continue;
            }

            $due = ($m->due_date ?? $m->movement_date)->toDateString();
            $bucket = $due < $today ? 'overdue' : ($due <= $soon ? 'due_soon' : 'not_due');
            $totals[$bucket] = bcadd($totals[$bucket], $open, 2);
            $items->push([
                'movement' => $m, 'open' => $open, 'due' => $due, 'bucket' => $bucket,
                'days_overdue' => $bucket === 'overdue' ? Carbon::parse($due)->diffInDays(Carbon::parse($today)) : 0,
            ]);
        }

        return $totals + [
            'open' => bcadd(bcadd($totals['overdue'], $totals['due_soon'], 2), $totals['not_due'], 2),
            'credit' => $remainingCredit, // borçları aşan alacak (bayinin alacağı)
            'items' => $items,
        ];
    }

    /**
     * Ekstre: tarih aralığındaki hareketler, devreden bakiye ve yürüyen bakiye.
     *
     * @return array{opening: string, rows: Collection, closing: string, debit_total: string, credit_total: string}
     */
    public function statement(CurrentAccount $account, string $from, string $to): array
    {
        $opening = (string) CurrentAccountMovement::where('current_account_id', $account->id)->where('movement_date', '<', $from)
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) b')->value('b');
        $opening = bcadd($opening, '0', 2);

        $movements = CurrentAccountMovement::where('current_account_id', $account->id)
            ->whereBetween('movement_date', [$from, $to])->orderBy('movement_date')->orderBy('id')->get();

        $running = $opening;
        $debitTotal = $creditTotal = '0.00';
        $rows = $movements->map(function ($m) use (&$running, &$debitTotal, &$creditTotal) {
            $running = bcadd($running, bcsub((string) $m->debit, (string) $m->credit, 2), 2);
            $debitTotal = bcadd($debitTotal, (string) $m->debit, 2);
            $creditTotal = bcadd($creditTotal, (string) $m->credit, 2);

            return ['movement' => $m, 'balance' => $running];
        });

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $running, 'debit_total' => $debitTotal, 'credit_total' => $creditTotal];
    }
}
