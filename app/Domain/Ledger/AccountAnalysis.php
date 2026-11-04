<?php

namespace App\Domain\Ledger;

use App\Domain\Support\LocalTime;
use App\Models\CurrentAccount;
use App\Models\CurrentAccountMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vade yaşlandırma ve ekstre (docs/03-is-kurallari.md §6, C9). Hiçbir şey saklanmaz; her şey hareketlerden türetilir.
 *
 * Eşleştirme: siparişe bağlı alacak (o siparişin kart / havale ödemesi, iadesi) önce o siparişin borcunu kapatır;
 * artanı ve siparişe bağlı olmayan alacaklar (toplu ödeme, alacak düzeltmesi, açılıştaki alacak) en eski vadeli
 * borçtan başlayarak kapatır (FIFO). Birbirini ters çeviren hareket çiftleri (iptal edilen sipariş/tahsilat) hesaba
 * katılmaz. Sonra öde siparişinin vadesi siparişten okunur: teslimde yazılan son ödeme tarihi; teslim edilmediyse
 * "teslim bekleniyor" (vadesi gelmemiş sayılır, sırada en sona kalır).
 */
class AccountAnalysis
{
    public const DUE_SOON_DAYS = 7;

    private const PENDING_DUE = '9999-12-31';

    /**
     * @return array{overdue: string, due_soon: string, not_due: string, open: string, credit: string, items: Collection}
     */
    public function aging(CurrentAccount $account, ?string $asOf = null): array
    {
        return $this->agingFromMovements($this->movements([$account->id])->get($account->id, collect()), $asOf);
    }

    /**
     * Birden çok hesap için yaşlandırma (cari listesi, dashboard, rapor, hatırlatma). Yalnızca borç bakiyeli
     * hesapların hareketleri tek sorguda yüklenir.
     *
     * @return array<int, array> hesap ID'sine göre
     */
    public function agingMany(Collection $accounts, ?string $asOf = null): array
    {
        $positive = $accounts->filter(fn ($a) => bccomp((string) $a->balance, '0', 2) > 0)->pluck('id')->all();
        $movements = $positive === [] ? collect() : $this->movements($positive);

        return $accounts->mapWithKeys(fn ($a) => [$a->id => in_array($a->id, $positive, true)
            ? $this->agingFromMovements($movements->get($a->id, collect()), $asOf)
            : [
                'overdue' => '0.00', 'due_soon' => '0.00', 'not_due' => '0.00', 'open' => '0.00',
                'credit' => bccomp((string) $a->balance, '0', 2) < 0 ? bcmul((string) $a->balance, '-1', 2) : '0.00',
                'items' => collect(),
            ]])->all();
    }

    /**
     * Hesap başına toplam borç (siparişler) ve alacak (ödeme ve iadeler); birbirini ters çeviren çiftler (iptaller) hariç.
     *
     * @param  list<int>  $accountIds
     * @return array<int, array{debit: string, credit: string}>
     */
    public function totals(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return CurrentAccountMovement::query()->whereIn('current_account_id', $accountIds)->whereNull('reverses_movement_id')
            ->whereNotExists(fn ($q) => $q->from('current_account_movements as r')->whereColumn('r.reverses_movement_id', 'current_account_movements.id'))
            ->groupBy('current_account_id')->selectRaw('current_account_id, SUM(debit) d, SUM(credit) c')->get()
            ->mapWithKeys(fn ($r) => [$r->current_account_id => ['debit' => bcadd((string) $r->d, '0', 2), 'credit' => bcadd((string) $r->c, '0', 2)]])->all();
    }

    /** Siparişlerin açık kalan tutarı (yalnızca açık olanlar): sipariş ID'si => tutar. */
    public function openByOrder(CurrentAccount $account): array
    {
        return $this->aging($account)['items']
            ->filter(fn ($i) => $i['movement']->type === 'order_debit' && $i['movement']->order_id)
            ->mapWithKeys(fn ($i) => [$i['movement']->order_id => $i['open']])->all();
    }

    public function agingFromMovements(Collection $movements, ?string $asOf = null): array
    {
        $today = $asOf ?? LocalTime::today();
        $soon = Carbon::parse($today)->addDays(self::DUE_SOON_DAYS)->toDateString();

        // Ters kayıt çiftlerini dışla.
        $reversed = $movements->pluck('reverses_movement_id')->filter()->flip();
        $effective = $movements->reject(fn ($m) => $m->reverses_movement_id !== null || $reversed->has($m->id));

        $debits = $effective->filter(fn ($m) => bccomp((string) $m->debit, '0', 2) > 0)
            ->sortBy(fn ($m) => $this->dueOf($m).'-'.str_pad((string) $m->id, 12, '0', STR_PAD_LEFT))
            ->values();
        $open = $debits->mapWithKeys(fn ($m) => [$m->id => bcadd((string) $m->debit, '0', 2)])->all();
        $orderDebits = $debits->filter(fn ($m) => $m->type === 'order_debit' && $m->order_id)->keyBy('order_id');

        // 1) Siparişe bağlı alacaklar önce o siparişi kapatır; artan genel alacağa geçer.
        $general = '0.00';
        foreach ($effective->filter(fn ($m) => bccomp((string) $m->credit, '0', 2) > 0) as $c) {
            $amount = bcadd((string) $c->credit, '0', 2);
            if ($c->order_id && ($d = $orderDebits->get($c->order_id))) {
                $applied = bccomp($amount, $open[$d->id], 2) >= 0 ? $open[$d->id] : $amount;
                $open[$d->id] = bcsub($open[$d->id], $applied, 2);
                $amount = bcsub($amount, $applied, 2);
            }
            $general = bcadd($general, $amount, 2);
        }

        // 2) Genel alacak en eski vadeden başlayarak (FIFO).
        $totals = ['overdue' => '0.00', 'due_soon' => '0.00', 'not_due' => '0.00'];
        $items = collect();
        foreach ($debits as $m) {
            $applied = bccomp($general, $open[$m->id], 2) >= 0 ? $open[$m->id] : $general;
            $general = bcsub($general, $applied, 2);
            $left = bcsub($open[$m->id], $applied, 2);
            if (bccomp($left, '0', 2) <= 0) {
                continue;
            }

            $due = $this->dueOf($m);
            $pending = $due === self::PENDING_DUE;
            $bucket = $pending ? 'not_due' : ($due < $today ? 'overdue' : ($due <= $soon ? 'due_soon' : 'not_due'));
            $totals[$bucket] = bcadd($totals[$bucket], $left, 2);
            $items->push([
                'movement' => $m, 'movement_id' => $m->id, 'order' => $m->type === 'order_debit' ? $m->order : null,
                'open' => $left, 'due' => $pending ? null : $due, 'pending' => $pending, 'bucket' => $bucket,
                'days_overdue' => $bucket === 'overdue' ? (int) Carbon::parse($due)->diffInDays(Carbon::parse($today)) : 0,
                'days_left' => ! $pending && $due >= $today ? (int) Carbon::parse($today)->diffInDays(Carbon::parse($due)) : null,
            ]);
        }

        return $totals + [
            'open' => bcadd(bcadd($totals['overdue'], $totals['due_soon'], 2), $totals['not_due'], 2),
            'credit' => $general, // borçları aşan alacak (bayinin alacağı)
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

    /** @param  list<int>  $accountIds */
    private function movements(array $accountIds): Collection
    {
        return CurrentAccountMovement::whereIn('current_account_id', $accountIds)
            ->with('order:id,business_id,order_no,status,payment_method,payment_status,payment_term_days,payment_due_on,grand_total,completed_at')
            ->orderBy('id')->get()->groupBy('current_account_id');
    }

    /** Sonra öde siparişinin vadesi siparişten (teslimde yazılır); diğerlerinde hareketin vadesi. */
    private function dueOf(CurrentAccountMovement $m): string
    {
        if ($m->type === 'order_debit' && $m->order?->payment_method === 'pay_later') {
            return $m->order->payment_due_on?->toDateString() ?? self::PENDING_DUE;
        }

        return ($m->due_date ?? $m->movement_date)->toDateString();
    }
}
