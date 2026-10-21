<?php

namespace App\Domain\Store\Payments;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Support\Decimal;
use App\Domain\Support\Money;
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
        $error = null;
        foreach ($plan as $index => $part) {
            try {
                $response = $this->iyzico->refund($transactions[$index]['transaction_id'], $part, $order->order_no, $ip);
                $ok = ($response['status'] ?? null) === 'success';
                $message = $ok ? null : mb_substr((string) ($response['errorMessage'] ?? 'iyzico iadeyi kabul etmedi.'), 0, 200);
            } catch (DomainException $e) {
                $ok = false;
                $message = $e->getMessage();
            }

            $results[] = ['transaction_id' => $transactions[$index]['transaction_id'], 'amount' => $part, 'status' => $ok ? 'succeeded' : 'failed', 'error' => $message];
            if ($ok) {
                $transactions[$index]['refunded'] = bcadd($transactions[$index]['refunded'] ?? '0.00', $part, 2);
                $succeeded = bcadd($succeeded, $part, 2);
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

    /** İki tutarın küçüğü (bcmath). */
    private static function min(string $a, string $b): string
    {
        return bccomp($a, $b, 2) <= 0 ? $a : $b;
    }
}
