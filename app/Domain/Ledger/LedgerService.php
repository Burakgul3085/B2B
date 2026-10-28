<?php

namespace App\Domain\Ledger;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Support\LocalTime;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Models\CurrentAccountMovement;
use App\Models\Order;
use App\Models\OrderReturn;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cari hareketleri (docs/03-is-kurallari.md §6). Stoğu değiştiren StockService gibi, cariyi değiştiren tek yer.
 *  C1 bakiye = SUM(borç) − SUM(alacak); current_accounts.balance aynı transaction'da, satır kilitliyken güncellenir.
 *  C2 hareket silinmez; ters kayıt (reverses_movement_id UNIQUE → bir hareket en fazla bir kez ters çevrilir).
 *  C3 sipariş borcu tek (source_key UNIQUE). C6 açılış bakiyesi ilişki başına bir kez. C7 düzeltmede açıklama zorunlu.
 * Bakiye işareti: pozitif = bayi borçlu (toptancı alacaklı).
 */
class LedgerService
{
    public function __construct(private CurrentActor $actor, private AuditLogger $audit) {}

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
            'movement_date' => LocalTime::today(),
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
            'movement_date' => LocalTime::today(),
            'order_id' => $order->id,
            'reverses_movement_id' => $original->id,
            'description' => "Sipariş {$order->order_no} iptali: {$reason}",
            'source_key' => "order:{$order->id}:debit_reversal",
        ]);
    }

    /** İade alacağı (iade kaydıyla aynı transaction'da). */
    public function postReturnCredit(OrderReturn $return, Order $order): CurrentAccountMovement
    {
        $this->assertInTransaction();
        $account = $this->lockAccount($order->dealer_relationship_id);

        return $this->write($account, [
            'type' => 'return_credit',
            'debit' => '0',
            'credit' => (string) $return->grand_total,
            'movement_date' => LocalTime::today(),
            'order_id' => $order->id,
            'description' => "Sipariş {$order->order_no} iadesi",
            'source_key' => "return:{$return->id}:credit",
        ]);
    }

    /**
     * Açılış bakiyesi: ilişki başına bir kez. Pozitif tutar bayinin borcu, negatif tutar bayinin alacağıdır.
     */
    public function setOpening(CurrentAccount $account, string $amount, string $date, ?string $note): CurrentAccountMovement
    {
        if (bccomp($amount, '0', 2) === 0) {
            throw new DomainException('Açılış bakiyesi sıfır olamaz.');
        }

        return DB::transaction(function () use ($account, $amount, $date, $note) {
            $account = $this->lockAccount($account->dealer_relationship_id);
            if (CurrentAccountMovement::where('source_key', "opening:{$account->id}")->exists()) {
                throw new DomainException('Bu cari hesabın açılış bakiyesi zaten girilmiş. Değişiklik için düzeltme hareketi kullanın.');
            }

            $debt = bccomp($amount, '0', 2) > 0;
            $movement = $this->write($account, [
                'type' => 'opening',
                'debit' => $debt ? $amount : '0',
                'credit' => $debt ? '0' : ltrim($amount, '-'),
                'movement_date' => $date,
                'due_date' => $debt ? $date : null,
                'description' => $note ?: 'Açılış bakiyesi',
                'source_key' => "opening:{$account->id}",
            ]);
            $this->audit->log('ledger.opening_set', $movement, [], ['amount' => $amount, 'date' => $date]);

            return $movement;
        });
    }

    /** Yetkili manuel düzeltme (C7): açıklama zorunlu. */
    public function adjust(CurrentAccount $account, string $direction, string $amount, string $date, string $note): CurrentAccountMovement
    {
        if (! in_array($direction, ['debit', 'credit'], true) || bccomp($amount, '0', 2) <= 0) {
            throw new DomainException('Geçersiz düzeltme.');
        }
        if (trim($note) === '') {
            throw new DomainException('Düzeltme için açıklama zorunludur.');
        }

        return DB::transaction(function () use ($account, $direction, $amount, $date, $note) {
            $account = $this->lockAccount($account->dealer_relationship_id);
            $debit = $direction === 'debit';
            $movement = $this->write($account, [
                'type' => $debit ? 'adjustment_debit' : 'adjustment_credit',
                'debit' => $debit ? $amount : '0',
                'credit' => $debit ? '0' : $amount,
                'movement_date' => $date,
                'due_date' => $debit ? $date : null,
                'description' => $note,
            ]);
            $this->audit->log('ledger.adjusted', $movement, [], compact('direction', 'amount', 'date', 'note'));

            return $movement;
        });
    }

    /** Manuel tahsilat (C4): tahsilat kaydı + alacak hareketi + bakiye tek transaction'da. */
    public function recordCollection(CurrentAccount $account, array $data, int $userId): CollectionEntry
    {
        return DB::transaction(function () use ($account, $data, $userId) {
            $account = $this->lockAccount($account->dealer_relationship_id);

            if (! empty($data['order_id']) && ! Order::where('id', $data['order_id'])->where('dealer_relationship_id', $account->dealer_relationship_id)->exists()) {
                throw new DomainException('Seçilen sipariş bu bayiye ait değil.');
            }

            $collection = CollectionEntry::create([
                'current_account_id' => $account->id,
                'collected_on' => $data['collected_on'],
                'amount' => $data['amount'],
                'method' => $data['method'],
                'order_id' => $data['order_id'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $userId,
            ]);

            $this->write($account, [
                'type' => 'collection',
                'debit' => '0',
                'credit' => (string) $collection->amount,
                'movement_date' => $collection->collected_on->toDateString(),
                'collection_id' => $collection->id,
                'order_id' => $collection->order_id,
                'description' => 'Tahsilat — '.CollectionEntry::METHODS[$collection->method].($collection->description ? ': '.$collection->description : ''),
                'source_key' => "collection:{$collection->id}",
            ]);
            $this->audit->log('ledger.collection_recorded', $collection, [], ['amount' => (string) $collection->amount, 'method' => $collection->method]);

            return $collection;
        });
    }

    /** Tahsilat iptali (C5): kayıt silinmez; ters borç hareketi yazılır, en fazla bir kez. */
    public function cancelCollection(CollectionEntry $collection, string $reason): void
    {
        DB::transaction(function () use ($collection, $reason) {
            $collection = CollectionEntry::whereKey($collection->id)->lockForUpdate()->firstOrFail();
            if ($collection->isCancelled()) {
                throw new DomainException('Bu tahsilat zaten iptal edilmiş.');
            }

            $account = CurrentAccount::whereKey($collection->current_account_id)->firstOrFail();
            $account = $this->lockAccount($account->dealer_relationship_id);
            $original = CurrentAccountMovement::where('source_key', "collection:{$collection->id}")->firstOrFail();

            $this->write($account, [
                'type' => 'reversal',
                'debit' => (string) $original->credit,
                'credit' => '0',
                'movement_date' => LocalTime::today(),
                'due_date' => LocalTime::today(),
                'collection_id' => $collection->id,
                'reverses_movement_id' => $original->id,
                'description' => 'Tahsilat iptali: '.$reason,
                'source_key' => "collection:{$collection->id}:reversal",
            ]);

            $collection->forceFill(['cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $this->audit->log('ledger.collection_cancelled', $collection, [], ['reason' => $reason]);
        });
    }

    public function lockAccount(int $relationshipId): CurrentAccount
    {
        return CurrentAccount::where('dealer_relationship_id', $relationshipId)->lockForUpdate()->firstOr(
            fn () => throw new DomainException('Bayinin cari hesabı bulunamadı.')
        );
    }

    private function write(CurrentAccount $account, array $data): CurrentAccountMovement
    {
        $actor = $this->actor->get();
        $movement = CurrentAccountMovement::create($data + [
            'current_account_id' => $account->id,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
        ]);

        $delta = bcsub((string) $movement->debit, (string) $movement->credit, 2);
        CurrentAccount::whereKey($account->id)->update(['balance' => DB::raw('balance + ('.$delta.')'), 'updated_at' => now()]);

        return $movement;
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('Cari hareketi bir veritabanı transaction içinde yazılmalıdır.');
        }
    }
}
