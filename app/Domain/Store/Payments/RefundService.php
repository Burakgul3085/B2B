<?php

namespace App\Domain\Store\Payments;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Support\Decimal;
use App\Domain\Support\Money;
use App\Domain\Support\Payments\IyzicoUnreachable;
use App\Models\Store\StoreOrder;
use App\Models\Store\StorePayment;
use App\Models\Store\StoreRefund;
use App\Models\Store\StoreReturn;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Para iadesi (docs/20-magaza-m7-raporu.md §3).
 *  - Kartla ödeme: iyzico'da her sepet kalemi ayrı işlemdir; tutar önce iade edilen ürünlerin işlemlerine, tam iadede
 *    teslimat işlemine, kalan olursa diğer işlemlere dağıtılır. Her işlemden iade edilen tutar ödeme kaydında tutulur;
 *    bir işlemden ödenenden fazlası iade edilemez.
 *  - Elle: kapıda ödemede havale veya iyzico panelinden yapılmış iade. Açıklama zorunludur.
 *  - Siparişte iade edilebilecek kalan tutar aşılamaz. Aynı siparişe eşzamanlı iki iade yapılamaz (kilit).
 *  - iyzico'da bir kısmı başarısız olursa başarılı kısım kaydedilir, hata yöneticiye gösterilir; kalan tekrar denenebilir.
 */
class RefundService
{
    public function __construct(private IyzicoClient $iyzico, private AuditLogger $audit, private CurrentActor $actor) {}

    /**
     * @param  array<int, int>  $itemQuantities  sipariş kalemi kimliği => iade edilen adet (dağıtımda öncelik)
     *
     * @throws DomainException
     */
    public function refund(StoreOrder $order, string $amount, string $method, array $itemQuantities = [], bool $includeFees = false,
        ?StoreReturn $return = null, ?string $note = null, ?string $ip = null): StoreRefund
    {
        $amount = Decimal::parse($amount, 2);
        if ($amount === null || bccomp($amount, '0', 2) <= 0) {
            throw new DomainException('Geçerli bir iade tutarı girin.');
        }
        $note = trim((string) $note) ?: null;
        if ($method === 'manual' && ! $note) {
            throw new DomainException('Elle yapılan iade için açıklama yazın (ör. havale tarihi ve dekont no).');
        }
        if (! in_array($method, ['iyzico', 'manual'], true) || ($method === 'iyzico' && $order->payment_method !== 'card')) {
            throw new DomainException('Bu sipariş için iade yöntemi geçersiz.');
        }

        if (StoreRefund::where('order_id', $order->id)->where('status', 'uncertain')->exists()) {
            throw new DomainException('Bu siparişte sonucu belirsiz bir iade var. Önce iyzico panelinden kontrol edip sipariş ekranında "Yapıldı" veya "Yapılmadı" olarak işaretleyin.');
        }
        $lock = Cache::lock("store-refund:{$order->id}", 60);
        if (! $lock->get()) {
            throw new DomainException('Bu sipariş için başka bir iade işlemi sürüyor. Biraz sonra tekrar deneyin.');
        }

        try {
            $order = $order->fresh();
            if (bccomp($amount, $order->refundableAmount(), 2) > 0) {
                throw new DomainException('İade tutarı, iade edilebilecek kalan tutarı ('.Money::format($order->refundableAmount()).') aşıyor.');
            }

            return $method === 'manual'
                ? $this->record($order, $return, 'manual', 'succeeded', $amount, $note, null, null)
                : $this->viaIyzico($order, $amount, $itemQuantities, $includeFees, $return, $note, $ip);
        } finally {
            $lock->release();
        }
    }

    private function viaIyzico(StoreOrder $order, string $amount, array $itemQuantities, bool $includeFees, ?StoreReturn $return, ?string $note, ?string $ip): StoreRefund
    {
        $payment = StorePayment::where('order_id', $order->id)->where('driver', 'iyzico')->where('status', 'succeeded')->latest('id')->first();
        $transactions = $payment?->item_transactions ?? [];
        if (! $transactions) {
            throw new DomainException('Bu ödemenin iyzico işlem bilgisi yok. İadeyi iyzico panelinden yapıp "elle" olarak işaretleyin.');
        }

        $plan = $this->allocate($order, $transactions, $amount, $itemQuantities, $includeFees);
        $results = [];
        $succeeded = '0.00';
        $failed = '0.00';
        $uncertain = '0.00';
        $error = null;
        foreach ($plan as $index => $part) {
            $state = 'failed';
            try {
                $response = $this->iyzico->refund($transactions[$index]['transaction_id'], $part, $order->order_no, $ip);
                $state = ($response['status'] ?? null) === 'success' ? 'succeeded' : 'failed';
                $message = $state === 'succeeded' ? null : mb_substr((string) ($response['errorMessage'] ?? 'iyzico iadeyi kabul etmedi.'), 0, 200);
            } catch (IyzicoUnreachable $e) {
                // Yanıt alınamadı: iade iyzico'da yapılmış olabilir. Tekrar denenmez; yönetici iyzico panelinden kontrol eder.
                $state = 'uncertain';
                $message = $e->getMessage();
            } catch (DomainException $e) {
                $message = $e->getMessage();
            }

            $results[] = ['index' => $index, 'transaction_id' => $transactions[$index]['transaction_id'], 'amount' => $part, 'status' => $state, 'error' => $message];
            if ($state === 'succeeded') {
                $transactions[$index]['refunded'] = bcadd($transactions[$index]['refunded'] ?? '0.00', $part, 2);
                $succeeded = bcadd($succeeded, $part, 2);
            } elseif ($state === 'uncertain') {
                $uncertain = bcadd($uncertain, $part, 2);
            } else {
                $failed = bcadd($failed, $part, 2);
                $error ??= $message;
            }
        }
        $payment->forceFill(['item_transactions' => $transactions])->save();

        $refund = null;
        if (bccomp($succeeded, '0', 2) > 0) {
            $refund = $this->record($order, $return, 'iyzico', 'succeeded', $succeeded, $note, array_values(array_filter($results, fn ($r) => $r['status'] === 'succeeded')), null);
        }
        if (bccomp($uncertain, '0', 2) > 0) {
            $this->record($order, $return, 'iyzico', 'uncertain', $uncertain, $note, array_values(array_filter($results, fn ($r) => $r['status'] === 'uncertain')),
                'iyzico\'ya ulaşılamadı; iade yapılmış olabilir.');
            throw new DomainException(($refund ? Money::format($succeeded).' iade edildi; ' : '').Money::format($uncertain)
                .' için iyzico\'dan yanıt alınamadı, iade yapılmış olabilir. iyzico panelinden kontrol edip sipariş ekranında "Yapıldı" veya "Yapılmadı" olarak işaretleyin; tekrar denemeyin.');
        }
        if (bccomp($failed, '0', 2) > 0) {
            $this->record($order, $return, 'iyzico', 'failed', $failed, $note, array_values(array_filter($results, fn ($r) => $r['status'] === 'failed')), $error);
            throw new DomainException(($refund ? Money::format($succeeded).' iade edildi; ' : '').Money::format($failed).' iade edilemedi: '.$error);
        }

        return $refund;
    }

    /**
     * Tutarı iyzico işlemlerine dağıtır.
     *
     * @return array<int, string> işlem sırası => iade tutarı
     *
     * @throws DomainException
     */
    private function allocate(StoreOrder $order, array $transactions, string $amount, array $itemQuantities, bool $includeFees): array
    {
        $capacity = array_map(fn ($t) => bcsub($t['paid_price'], $t['refunded'] ?? '0.00', 2), $transactions);
        $quantities = $order->items()->pluck('quantity', 'id');

        // Öncelik: iade edilen ürünlerin payı, tam iadede teslimat ücreti; ardından kalan kapasiteler.
        $targets = [];
        foreach ($transactions as $i => $t) {
            if (isset($itemQuantities[(int) $t['item_id']]) && $quantities->has((int) $t['item_id'])) {
                $share = Decimal::round(bcdiv(bcmul($t['paid_price'], (string) $itemQuantities[(int) $t['item_id']], 4), (string) $quantities[(int) $t['item_id']], 4), 2);
                $targets[$i] = self::min($share, $capacity[$i]);
            } elseif ($includeFees && $t['item_id'] === 'teslimat') {
                $targets[$i] = $capacity[$i];
            }
        }

        $plan = [];
        $left = $amount;
        foreach ([$targets, $capacity] as $round) {
            foreach ($round as $i => $limit) {
                $available = bcsub($capacity[$i], $plan[$i] ?? '0.00', 2);
                $take = self::min(self::min($limit, $available), $left);
                if (bccomp($take, '0', 2) > 0) {
                    $plan[$i] = bcadd($plan[$i] ?? '0.00', $take, 2);
                    $left = bcsub($left, $take, 2);
                }
            }
        }
        if (bccomp($left, '0', 2) > 0) {
            throw new DomainException('İade tutarı iyzico\'da iade edilebilecek tutarı aşıyor.');
        }
        ksort($plan);

        return $plan;
    }

    private function record(StoreOrder $order, ?StoreReturn $return, string $method, string $status, string $amount, ?string $note, ?array $transactions, ?string $error): StoreRefund
    {
        $actor = $this->actor->get();

        return DB::transaction(function () use ($order, $return, $method, $status, $amount, $note, $transactions, $error, $actor) {
            $refund = StoreRefund::create([
                'order_id' => $order->id, 'return_id' => $return?->id, 'method' => $method, 'status' => $status, 'amount' => $amount,
                'note' => $note ? mb_substr($note, 0, 300) : null, 'transactions' => $transactions, 'error_message' => $error ? mb_substr($error, 0, 300) : null,
                'created_by_type' => $actor['type'], 'created_by_id' => $actor['id'],
            ]);

            if ($status === 'succeeded') {
                $locked = StoreOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $total = bcadd((string) $locked->refunded_total, $amount, 2);
                $locked->forceFill([
                    'refunded_total' => $total,
                    'payment_status' => bccomp($total, (string) $locked->total, 2) >= 0 ? 'refunded' : 'partially_refunded',
                ])->save();
                $locked->events()->create(['status' => 'refund', 'note' => Money::format($amount).' iade edildi ('.($method === 'iyzico' ? 'karta' : 'elle').').'.($note ? ' '.$note : ''),
                    'actor_type' => $actor['type'], 'actor_id' => $actor['id']]);
                $order->setRawAttributes($locked->getAttributes(), true);
            }

            $this->audit->log($status === 'succeeded' ? 'store.refund_succeeded' : 'store.refund_failed', $order, [],
                ['order_no' => $order->order_no, 'amount' => $amount, 'method' => $method, 'error' => $error]);

            return $refund;
        });
    }

    /**
     * Sonucu belirsiz iadeyi yönetici iyzico panelinden kontrol edip işaretler: yapıldıysa siparişe işlenir (tutar ve
     * işlem kalanları), yapılmadıysa başarısız sayılır ve iade yeniden yapılabilir.
     *
     * @throws DomainException
     */
    public function resolveUncertain(StoreRefund $refund, bool $done): void
    {
        DB::transaction(function () use ($refund, $done) {
            $refund = StoreRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== 'uncertain') {
                throw new DomainException('Bu iade zaten sonuçlandırılmış.');
            }
            $order = StoreOrder::findOrFail($refund->order_id);
            $actor = $this->actor->get();

            if (! $done) {
                $refund->forceFill(['status' => 'failed', 'error_message' => 'iyzico panelinde yapılmadığı görüldü (yönetici).'])->save();
                $this->audit->log('store.refund_uncertain_failed', $order, [], ['order_no' => $order->order_no, 'amount' => (string) $refund->amount]);

                return;
            }

            $payment = StorePayment::where('order_id', $order->id)->where('driver', 'iyzico')->where('status', 'succeeded')->latest('id')->first();
            if ($payment) {
                $transactions = $payment->item_transactions ?? [];
                foreach ($refund->transactions ?? [] as $t) {
                    foreach ($transactions as $i => $pt) {
                        if ($pt['transaction_id'] === $t['transaction_id']) {
                            $transactions[$i]['refunded'] = bcadd($pt['refunded'] ?? '0.00', (string) $t['amount'], 2);
                        }
                    }
                }
                $payment->forceFill(['item_transactions' => $transactions])->save();
            }
            $refund->forceFill(['status' => 'resolved', 'error_message' => 'Sonuç belirsizdi; iyzico panelinde yapıldığı doğrulandı (aşağıdaki kayıt).'])->save();
            $this->record($order, $refund->return_id ? StoreReturn::find($refund->return_id) : null, 'iyzico', 'succeeded', (string) $refund->amount,
                trim(($refund->note ? $refund->note.' · ' : '').'iyzico panelinde doğrulandı'), $refund->transactions, null);
            if ($refund->return_id && ($return = StoreReturn::find($refund->return_id)) && $return->status === 'received') {
                $return->forceFill(['status' => 'refunded', 'refund_amount' => $refund->amount, 'refunded_at' => now(), 'closed_at' => now()])->save();
            }
            $this->audit->log('store.refund_uncertain_done', $order, [], ['order_no' => $order->order_no, 'amount' => (string) $refund->amount,
                'actor' => $actor['type']]);
        });
    }

    /** İki tutarın küçüğü (bcmath). */
    private static function min(string $a, string $b): string
    {
        return bccomp($a, $b, 2) <= 0 ? $a : $b;
    }
}
