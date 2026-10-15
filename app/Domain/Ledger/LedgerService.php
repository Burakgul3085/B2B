<?php

namespace App\Domain\Ledger;

use App\Domain\Auth\CurrentActor;
use App\Models\CurrentAccount;
use App\Models\CurrentAccountMovement;
use App\Models\Order;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cari hareketleri (docs/03-is-kurallari.md §6).
 *  C1 bakiye = SUM(borç) − SUM(alacak); current_accounts.balance aynı transaction'da, satır kilitliyken güncellenir.
 *  C2 hareket silinmez; ters kayıt (reverses_movement_id UNIQUE → bir hareket en fazla bir kez ters çevrilir).
 *  C3 sipariş borcu tek: source_key UNIQUE.
 */
class LedgerService
{
    public function __construct(private CurrentActor $actor) {}

    /** Sipariş borcu. Çağıran transaction açmış olmalı. */
    public function postOrderDebit(Order $order): CurrentAccountMovement
    {
        $this->assertInTransaction();
        $account = $this->lockAccount($order->dealer_relationship_id);
        $termDays = (int) $order->relationship->payment_term_days;

        return $this->write($account, [
            'type' => 'order_debit',
            'debit' => (string) $order->grand_total,
            'credit' => '0',
            'movement_date' => now()->toDateString(),
            'due_date' => now()->addDays($termDays)->toDateString(),
            'order_id' => $order->id,
            'description' => "Sipariş {$order->order_no}",
            'source_key' => "order:{$order->id}:debit",
        ]);
    }

    /** Sipariş borcunun ters kaydı (iptal). */
    public function reverseOrderDebit(Order $order, string $reason): CurrentAccountMovement
    {
        $this->assertInTransaction();
        $account = $this->lockAccount($order->dealer_relationship_id);

        $original = CurrentAccountMovement::where('source_key', "order:{$order->id}:debit")->firstOrFail();

        return $this->write($account, [
            'type' => 'reversal',
            'debit' => '0',
            'credit' => (string) $original->debit,
            'movement_date' => now()->toDateString(),
            'order_id' => $order->id,
            'reverses_movement_id' => $original->id,
            'description' => "Sipariş {$order->order_no} iptali: {$reason}",
            'source_key' => "order:{$order->id}:debit_reversal",
        ]);
    }

    public function lockAccount(int $relationshipId): CurrentAccount
    {
        return CurrentAccount::where('dealer_relationship_id', $relationshipId)->lockForUpdate()->firstOr(
            fn () => throw new DomainException('Bayinin cari hesabı bulunamadı.')
        );
    }

    public function write(CurrentAccount $account, array $data): CurrentAccountMovement
    {
        $actor = $this->actor->get();
        $movement = CurrentAccountMovement::create($data + [
            'current_account_id' => $account->id,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
        ]);

        $delta = bcsub((string) $movement->debit, (string) $movement->credit, 2);
        CurrentAccount::whereKey($account->id)->update(['balance' => DB::raw('balance + '.$delta), 'updated_at' => now()]);

        return $movement;
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('Cari hareketi bir veritabanı transaction içinde yazılmalıdır.');
        }
    }
}
