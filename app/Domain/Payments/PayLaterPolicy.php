<?php

namespace App\Domain\Payments;

use App\Domain\Ledger\AccountAnalysis;
use App\Domain\Support\Money;
use App\Models\CurrentAccount;
use App\Models\DealerRelationship;
use App\Models\Order;

/**
 * "Sonra öde" kuralları (docs/03-is-kurallari.md §6.1). Bayi seçebilmek için:
 *  - toptancı izin vermiş olmalı,
 *  - vadesi geçmiş borcu olmamalı,
 *  - borç limiti verildiyse: cari bakiye + cariye henüz yazılmamış açık sonra öde siparişleri + bu sipariş ≤ limit.
 * Kart ve havale bu kurallardan etkilenmez.
 */
class PayLaterPolicy
{
    public function __construct(private AccountAnalysis $analysis) {}

    /**
     * @param  string  $amount  bu siparişin cariye henüz yazılmamış tutarı (yazıldıysa 0)
     * @return string|null seçilemiyorsa nedeni
     */
    public function blockReason(DealerRelationship $relationship, string $amount, ?int $exceptOrderId = null): ?string
    {
        if (! config('b2b.pay_later_enabled')) {
            return 'Sonra öde seçeneği şu anda kullanılamıyor. Kartla veya havaleyle ödeyebilirsiniz.';
        }
        if (! $relationship->allow_pay_later) {
            return 'Sonra öde seçeneği hesabınızda açık değil.';
        }

        $account = CurrentAccount::where('dealer_relationship_id', $relationship->id)->first();
        if (! $account) {
            return 'Cari hesabınız bulunamadı.';
        }

        $overdue = $this->analysis->aging($account)['overdue'];
        if (bccomp($overdue, '0', 2) > 0) {
            return 'Vadesi geçmiş '.Money::format($overdue).' ödemeniz olduğu için sonra öde şu anda kullanılamıyor. Kartla veya havaleyle ödeyebilirsiniz.';
        }

        if ($relationship->credit_limit !== null) {
            $pending = (string) Order::where('dealer_relationship_id', $relationship->id)
                ->where('payment_method', 'pay_later')->whereNull('ledger_applied_at')
                ->whereIn('status', ['submitted', 'in_review', 'approved', 'preparing', 'shipped', 'cancel_requested'])
                ->when($exceptOrderId, fn ($q, $id) => $q->whereKeyNot($id))
                ->sum('grand_total');
            $exposure = bcadd(bcadd((string) $account->balance, $pending, 2), $amount, 2);
            if (bccomp($exposure, (string) $relationship->credit_limit, 2) > 0) {
                return 'Bu siparişle borç limitiniz ('.Money::format($relationship->credit_limit).') aşılacağı için sonra öde kullanılamıyor. Kartla veya havaleyle ödeyebilirsiniz.';
            }
        }

        return null;
    }
}
