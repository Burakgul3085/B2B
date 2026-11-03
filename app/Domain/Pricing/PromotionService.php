<?php

namespace App\Domain\Pricing;

use App\Domain\Support\Decimal;
use App\Domain\Support\Money;
use App\Models\Category;
use App\Models\DealerRelationship;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use DomainException;
use Illuminate\Support\Collection;

/**
 * B2B kupon ve kampanyaları (docs/03-is-kurallari.md §4.4).
 *  - Siparişte tek sipariş indirimi: bayi kod girdiyse kupon, girmediyse koşulu tutan en avantajlı otomatik kampanya.
 *  - Ürün / kategori indirim kurallarının ve bayiye özel fiyatın üzerine uygulanır; taban satırların indirimli
 *    KDV hariç tutarıdır (net). Kapsam (kategori alt kategorileriyle, ürün) varsa yalnızca kapsamdaki satırlar sayılır.
 *  - İndirim kapsamdaki satırlara net tutarlarıyla orantılı paylaştırılır; her satırda en az 0,01 ₺ kalır. KDV
 *    indirimli net üzerinden hesaplanır (fatura ve cari doğru çıkar).
 *  - Tutar her zaman sunucuda hesaplanır. Kullanım sınırları sipariş gönderilirken kampanya satırı kilitlenerek yeniden
 *    denetlenir (reserve); reddedilen / iptal edilen siparişin kullanımı geri verilir (release).
 */
class PromotionService
{
    public static function normalizeCode(?string $code): ?string
    {
        $code = mb_strtoupper(preg_replace('/\s+/', '', (string) $code), 'UTF-8');

        return $code === '' ? null : $code;
    }

    /**
     * Sepete uygulanacak sipariş indirimi. Kupon geçersizse nedeni döner ve (varsa) kampanya uygulanır.
     *
     * @param  array<int, array{product: Product, net: string}>  $lines  satır anahtarı → ürün ve indirimli net
     * @return array{applied: ?AppliedPromotion, error: ?string, next: ?array{promotion: Promotion, missing: string}}
     */
    public function evaluate(DealerRelationship $relationship, array $lines, ?string $code): array
    {
        if ($lines === []) {
            return ['applied' => null, 'error' => null, 'next' => null];
        }

        $error = null;
        if ($code = self::normalizeCode($code)) {
            $coupon = Promotion::where('code', $code)->first();
            $error = $coupon ? $this->refusal($coupon, $relationship, $lines) : 'Bu kupon kodu geçerli değil.';
            if ($error === null) {
                $applied = $this->apply($coupon, $lines);
                if ($applied) {
                    return ['applied' => $applied, 'error' => null, 'next' => null];
                }
                $error = 'Bu kupon sepetinizdeki ürünlere uygulanamıyor.';
            }
        }

        $best = null;
        foreach ($this->campaigns() as $campaign) {
            if ($this->refusal($campaign, $relationship, $lines) === null && ($applied = $this->apply($campaign, $lines))
                && ($best === null || bccomp($applied->amount, $best->amount, 2) > 0)) {
                $best = $applied;
            }
        }

        return ['applied' => $best, 'error' => $error, 'next' => $this->next($relationship, $lines, $best)];
    }

    /**
     * Sipariş gönderilirken: kampanya satırı kilitlenir, koşullar ve kullanım sınırları yeniden denetlenir, kullanım yazılır.
     * Koşul artık tutmuyorsa istisna (sipariş oluşmaz; bayi sepeti yeniden görür).
     */
    public function reserve(Order $order, AppliedPromotion $applied, DealerRelationship $relationship, array $lines): void
    {
        $promotion = Promotion::whereKey($applied->promotion->id)->lockForUpdate()->first();
        if (! $promotion || ($reason = $this->refusal($promotion, $relationship, $lines, locking: true)) !== null) {
            throw new DomainException(($reason ?? 'Kupon / kampanya artık geçerli değil.').' Sepetinizi kontrol edip yeniden gönderin.');
        }
        $fresh = $this->apply($promotion, $lines);
        if (! $fresh || bccomp($fresh->amount, $applied->amount, 2) !== 0) {
            throw new DomainException('Kupon / kampanya tutarı değişti. Sepetinizi kontrol edip yeniden gönderin.');
        }

        PromotionRedemption::create([
            'promotion_id' => $promotion->id, 'order_id' => $order->id,
            'dealer_relationship_id' => $relationship->id, 'amount' => $applied->amount,
        ]);
    }

    /** Sipariş reddedildi / iptal edildi: kullanım hakkı geri verilir. */
    public function release(Order $order): void
    {
        PromotionRedemption::where('order_id', $order->id)->whereNull('released_at')->update(['released_at' => now()]);
    }

    /**
     * Kısmi onayda sipariş indirimi onaylanan tutarlara göre yeniden hesaplanır (siparişteki anlık kopyayla; kampanya
     * sonradan değişse de sipariş değişmez). Yüzde indirim yeniden uygulanır; tutar indirimi ilk tutarı ve tabanı geçemez.
     *
     * @param  array<int, string>  $nets  kapsamdaki satırların onaylanan indirimli neti
     * @return array<int, string> satır anahtarı → pay
     */
    public static function reapply(Order $order, array $nets): array
    {
        if ($order->promotion_type === null || $nets === []) {
            return array_map(fn () => '0.00', $nets);
        }
        $amount = $order->promotion_type === 'percent'
            ? self::percentAmount(self::sum($nets), (string) $order->promotion_value, $order->promotion_max !== null ? (string) $order->promotion_max : null)
            : (string) $order->promotion_discount;

        return self::distribute($amount, $nets);
    }

    /**
     * Tutarı satırlara net tutarlarıyla orantılı paylaştırır; her satırda en az 0,01 ₺ kalır (sığmayan kuruş uygulanmaz).
     *
     * @param  array<int, string>  $nets
     * @return array<int, string>
     */
    public static function distribute(string $amount, array $nets): array
    {
        $shares = array_map(fn () => '0.00', $nets);
        $remaining = bcadd($amount, '0', 2);
        $base = self::sum($nets);
        foreach ($nets as $key => $net) {
            if (bccomp($remaining, '0', 2) <= 0 || bccomp($base, '0', 2) <= 0) {
                break;
            }
            $cap = bcsub($net, '0.01', 2);
            $share = bccomp($base, $net, 2) === 0 ? $remaining : Decimal::round(bcdiv(bcmul($remaining, $net, 10), $base, 10), 2);
            $share = bccomp($share, $cap, 2) > 0 ? $cap : $share;
            $share = bccomp($share, '0', 2) < 0 ? '0.00' : $share;
            $shares[$key] = $share;
            $remaining = bcsub($remaining, $share, 2);
            $base = bcsub($base, $net, 2);
        }

        return $shares;
    }

    /** Kampanyanın bu bayiye ve sepete uygulanmama nedeni; uygulanabiliyorsa null. */
    public function refusal(Promotion $promotion, DealerRelationship $relationship, array $lines, bool $ignoreMinimum = false, bool $locking = false): ?string
    {
        if (! $promotion->is_active) {
            return 'Bu kupon şu an kullanılmıyor.';
        }
        if ($promotion->starts_at !== null && $promotion->starts_at->isFuture()) {
            return 'Bu kupon '.$promotion->starts_at->timezone('Europe/Istanbul')->format('d.m.Y H:i').' tarihinde başlıyor.';
        }
        if ($promotion->ends_at !== null && ! $promotion->ends_at->isFuture()) {
            return 'Bu kuponun süresi dolmuş.';
        }
        if (! $this->targets($promotion, $relationship)) {
            return 'Bu kupon hesabınız için geçerli değil.';
        }
        $base = self::sum($this->eligibleNets($promotion, $lines));
        if (bccomp($base, '0', 2) <= 0) {
            return 'Bu kupon sepetinizdeki ürünler için geçerli değil.';
        }
        if (! $ignoreMinimum && $promotion->min_subtotal !== null && bccomp($base, (string) $promotion->min_subtotal, 2) < 0) {
            return 'Bu kupon '.Money::format($promotion->min_subtotal).' (KDV hariç) ve üzeri '.($promotion->isScoped() ? 'kapsamdaki ürün tutarında' : 'siparişlerde').' geçerli.';
        }
        // Gönderimde kilitli okuma: işlemin başındaki anlık görüntü değil, son işlenen kullanımlar sayılır.
        $used = fn () => $promotion->redemptions()->whereNull('released_at')->when($locking, fn ($q) => $q->lockForUpdate());
        if ($promotion->usage_limit !== null && $used()->count() >= $promotion->usage_limit) {
            return 'Bu kuponun kullanım sınırı dolmuş.';
        }
        if ($promotion->per_dealer_limit !== null
            && $used()->where('dealer_relationship_id', $relationship->id)->count() >= $promotion->per_dealer_limit) {
            return 'Bu kuponu kullanım hakkınız doldu.';
        }

        return null;
    }

    /** Uygulanabilir kampanyaların bayiye görünenleri (bayi panelinde "Size özel kampanyalar"). */
    public function visibleFor(DealerRelationship $relationship): Collection
    {
        return Promotion::live()->whereNull('code')->orderBy('id')->get()
            ->filter(fn (Promotion $p) => $this->targets($p, $relationship)
                && ($p->usage_limit === null || $p->usedCount() < $p->usage_limit))
            ->values();
    }

    private function apply(Promotion $promotion, array $lines): ?AppliedPromotion
    {
        $nets = $this->eligibleNets($promotion, $lines);
        $base = self::sum($nets);
        $amount = $promotion->type === 'percent'
            ? self::percentAmount($base, (string) $promotion->value, $promotion->max_discount !== null ? (string) $promotion->max_discount : null)
            : bcadd((string) $promotion->value, '0', 2);
        $shares = self::distribute($amount, $nets);
        $total = self::sum($shares);

        return bccomp($total, '0', 2) > 0 ? new AppliedPromotion($promotion, $total, $shares) : null;
    }

    private static function percentAmount(string $base, string $percent, ?string $max): string
    {
        $amount = Decimal::round(bcdiv(bcmul($base, $percent, 10), '100', 10), 2);

        return $max !== null && bccomp($amount, $max, 2) > 0 ? bcadd($max, '0', 2) : $amount;
    }

    /** @return array<int, string> kapsamdaki satırların neti */
    private function eligibleNets(Promotion $promotion, array $lines): array
    {
        $categories = $promotion->category_ids ? $this->withDescendants($promotion->category_ids) : [];
        $products = array_map('intval', $promotion->product_ids ?? []);
        $nets = [];
        foreach ($lines as $key => $line) {
            $inScope = ! $promotion->isScoped()
                || in_array($line['product']->id, $products, true)
                || ($line['product']->category_id && in_array((int) $line['product']->category_id, $categories, true));
            if ($inScope && bccomp($line['net'], '0.01', 2) > 0) {
                $nets[$key] = bcadd($line['net'], '0', 2);
            }
        }

        return $nets;
    }

    private function targets(Promotion $promotion, DealerRelationship $relationship): bool
    {
        return match ($promotion->target) {
            'group' => $promotion->dealer_group_id !== null && (int) $relationship->dealer_group_id === (int) $promotion->dealer_group_id,
            'dealers' => in_array($relationship->id, array_map('intval', $promotion->dealer_ids ?? []), true),
            default => true,
        };
    }

    /** @return list<int> */
    private function withDescendants(array $ids): array
    {
        $parents = Category::pluck('parent_id', 'id');
        $result = array_map('intval', $ids);
        do {
            $added = $parents->filter(fn ($parent, $id) => $parent !== null && in_array((int) $parent, $result, true) && ! in_array((int) $id, $result, true))->keys()->all();
            $result = array_merge($result, array_map('intval', $added));
        } while ($added !== []);

        return $result;
    }

    /** @return array{promotion: Promotion, missing: string}|null en düşük tutara takılan en yakın kampanya */
    private function next(DealerRelationship $relationship, array $lines, ?AppliedPromotion $applied): ?array
    {
        $best = null;
        foreach ($this->campaigns() as $campaign) {
            if ($campaign->min_subtotal === null || $campaign->id === $applied?->promotion->id) {
                continue;
            }
            $base = self::sum($this->eligibleNets($campaign, $lines));
            if (bccomp($base, (string) $campaign->min_subtotal, 2) >= 0 || $base === '0.00') {
                continue;
            }
            $potential = $campaign->type === 'percent'
                ? self::percentAmount((string) $campaign->min_subtotal, (string) $campaign->value, $campaign->max_discount !== null ? (string) $campaign->max_discount : null)
                : (string) $campaign->value;
            if ($applied && bccomp($potential, $applied->amount, 2) <= 0) {
                continue;
            }
            if ($this->refusal($campaign, $relationship, $lines, ignoreMinimum: true) !== null) {
                continue;
            }
            $missing = bcsub((string) $campaign->min_subtotal, $base, 2);
            if ($best === null || bccomp($missing, $best['missing'], 2) < 0) {
                $best = ['promotion' => $campaign, 'missing' => $missing];
            }
        }

        return $best;
    }

    private function campaigns(): Collection
    {
        return Promotion::live()->whereNull('code')->orderBy('id')->get();
    }

    private static function sum(array $values): string
    {
        return array_reduce($values, fn ($carry, $v) => bcadd($carry, (string) $v, 2), '0.00');
    }
}
