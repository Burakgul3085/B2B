<?php

namespace App\Domain\Store\Discounts;

use App\Domain\Store\Cart\CartLine;
use App\Domain\Store\Cart\CartSummary;
use App\Domain\Support\Decimal;
use App\Domain\Support\Money;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreDiscountCode;
use App\Models\Store\StoreDiscountRedemption;
use App\Models\Store\StoreOrder;

/**
 * Kupon ve otomatik kampanya kuralları (docs/21-magaza-m8-raporu.md §1).
 *  - Siparişte tek indirim uygulanır: müşteri kupon girdiyse kupon, girmediyse koşulları tutan en avantajlı kampanya.
 *  - İndirim ürünlerin KDV dahil ara toplamına uygulanır ve satırlara tutarlarıyla orantılı paylaştırılır; her satırda
 *    en az 0,01 ₺ kalır (iyzico sepetinde sıfır tutarlı kalem olamaz).
 *  - Kişiye bağlı koşullar (kişi başı kullanım, yalnızca ilk sipariş) e-posta ve telefon bilindiğinde, yani ödeme
 *    adımında denetlenir; sepette yalnızca genel koşullar gösterilir. Kullanım sınırı sipariş oluşurken kilitle yeniden denetlenir.
 *  - Kapsam: indirim belirli kategorilere (alt kategorileriyle) ve/veya ürünlere sınırlanabilir; o zaman en düşük tutar,
 *    yüzde ve paylaştırma yalnızca kapsamdaki satırlar üzerinden hesaplanır.
 *  - Toplu kodlar (store_discount_codes) bağlı oldukları indirimin kurallarını taşır ve her biri bir kez kullanılır.
 */
class DiscountService
{
    public static function normalizeCode(?string $code): ?string
    {
        $code = strtoupper(preg_replace('/\s+/', '', (string) $code));

        return $code === '' ? null : $code;
    }

    /**
     * Sepete uygulanacak indirim. Kupon geçersizse nedeni döner ve (varsa) kampanya uygulanır.
     *
     * @return array{applied: ?AppliedDiscount, couponError: ?string}
     */
    public function evaluate(CartSummary $summary, ?string $couponCode, ?string $email = null, ?string $phone = null): array
    {
        $code = self::normalizeCode($couponCode);
        if ($summary->orderable() === []) {
            return ['applied' => null, 'couponError' => null];
        }

        if ($code) {
            $coupon = $this->findByCode($code);
            $error = $coupon ? $this->refusal($coupon, $this->eligibleBase($coupon, $summary), $email, $phone, null, $code) : 'Bu kupon kodu geçerli değil.';
            if (! $error) {
                return ['applied' => $this->apply($coupon, $summary, $code), 'couponError' => null];
            }

            return ['applied' => $this->bestCampaign($summary, $email, $phone), 'couponError' => $error];
        }

        return ['applied' => $this->bestCampaign($summary, $email, $phone), 'couponError' => null];
    }

    /** Kupon kodu: indirimin kendi kodu veya toplu üretilmiş kodlarından biri. */
    public function findByCode(string $code): ?StoreDiscount
    {
        return StoreDiscount::where('code', $code)->where('codes_only', false)->first()
            ?? StoreDiscountCode::with('discount')->where('code', $code)->first()?->discount;
    }

    /**
     * İndirimin bu sepete ve (biliniyorsa) bu müşteriye uygulanamama nedeni; uygulanabiliyorsa null.
     *
     * @param  string  $base  kapsamdaki ürünlerin KDV dahil toplamı
     * @param  string|null  $code  kullanılan kod (toplu kodlar tek kullanımlıktır)
     */
    public function refusal(StoreDiscount $discount, string $base, ?string $email, ?string $phone, ?int $exceptOrderId = null, ?string $code = null): ?string
    {
        if (! $discount->isLive()) {
            return $discount->isCoupon() ? 'Bu kuponun süresi dolmuş veya kupon kullanımda değil.' : 'Kampanya sona erdi.';
        }
        if (bccomp($base, '0', 2) <= 0) {
            return 'Sepetinizde bu '.($discount->isCoupon() ? 'kupona' : 'kampanyaya').' uygun ürün yok.';
        }
        if ($code !== null && $code !== $discount->code
            && StoreDiscountRedemption::where('code', $code)->where('status', 'active')->exists()) {
            return 'Bu kupon kodu daha önce kullanılmış.';
        }
        if ($discount->min_subtotal !== null && bccomp($base, (string) $discount->min_subtotal, 2) < 0) {
            return 'Bu '.($discount->isCoupon() ? 'kupon' : 'kampanya').' '.Money::format($discount->min_subtotal).' ve üzeri alışverişlerde geçerli.';
        }
        if ($discount->usage_limit !== null
            && StoreDiscountRedemption::where('discount_id', $discount->id)->where('status', 'active')->count() >= $discount->usage_limit) {
            return 'Bu kuponun kullanım sınırı doldu.';
        }
        if ($email || $phone) {
            $mine = fn ($q) => $q->where(fn ($q) => $q->when($email, fn ($q) => $q->where('email', $email))->when($phone, fn ($q) => $q->orWhere('phone', $phone)));
            if ($discount->per_customer_limit !== null
                && StoreDiscountRedemption::where('discount_id', $discount->id)->where('status', 'active')->where($mine)->count() >= $discount->per_customer_limit) {
                return 'Bu kuponu kullanım hakkınız doldu.';
            }
            if ($discount->first_order_only
                && StoreOrder::whereNotIn('status', ['payment_failed', 'cancelled'])->where($mine)->when($exceptOrderId, fn ($q, $id) => $q->whereKeyNot($id))->exists()) {
                return 'Bu '.($discount->isCoupon() ? 'kupon' : 'kampanya').' yalnızca ilk siparişte geçerli.';
            }
        }

        return null;
    }

    private function bestCampaign(CartSummary $summary, ?string $email, ?string $phone): ?AppliedDiscount
    {
        $best = null;
        foreach (StoreDiscount::whereNull('code')->where('is_active', true)->orderBy('id')->get() as $campaign) {
            if ($this->refusal($campaign, $this->eligibleBase($campaign, $summary), $email, $phone)) {
                continue;
            }
            $applied = $this->apply($campaign, $summary);
            // Ürün indirimi büyük olan; eşitse ücretsiz teslimat sunan tercih edilir.
            if (! $best || bccomp($applied->amount, $best->amount, 2) > 0
                || (bccomp($applied->amount, $best->amount, 2) === 0 && $applied->freeShipping && ! $best->freeShipping)) {
                $best = $applied;
            }
        }

        return $best;
    }

    /** Kapsamdaki satırlar (sipariş verilebilir satırların sırasıyla, anahtarlar korunur). @return array<int, CartLine> */
    public function eligibleLines(StoreDiscount $discount, CartSummary $summary): array
    {
        $lines = $summary->orderable();
        $categories = array_map('intval', $discount->category_ids ?? []);
        $products = array_map('intval', $discount->product_ids ?? []);
        if (! $categories && ! $products) {
            return $lines;
        }
        $categories = $categories ? self::withDescendants($categories) : [];

        return array_filter($lines, fn (CartLine $l) => in_array($l->product->id, $products, true)
            || ($l->product->category_id && in_array((int) $l->product->category_id, $categories, true)));
    }

    public function eligibleBase(StoreDiscount $discount, CartSummary $summary): string
    {
        return array_reduce($this->eligibleLines($discount, $summary), fn (string $sum, CartLine $l) => bcadd($sum, $l->total(), 2), '0.00');
    }

    /** @param  list<int>  $ids @return list<int> */
    private static function withDescendants(array $ids): array
    {
        $all = $ids;
        for ($level = $ids; $level;) {
            $level = StoreCategory::whereIn('parent_id', $level)->pluck('id')->all();
            $all = array_merge($all, $level);
        }

        return array_values(array_unique($all));
    }

    private function apply(StoreDiscount $discount, CartSummary $summary, ?string $code = null): AppliedDiscount
    {
        $eligible = $this->eligibleLines($discount, $summary);
        $lines = array_values($eligible);
        $base = $this->eligibleBase($discount, $summary);

        $amount = match ($discount->type) {
            'percent' => Decimal::round(bcdiv(bcmul($base, (string) $discount->value, 6), '100', 6), 2),
            'fixed' => bcadd((string) $discount->value, '0', 2),
            default => '0.00',
        };
        if ($discount->type === 'percent' && $discount->max_discount !== null && bccomp($amount, (string) $discount->max_discount, 2) > 0) {
            $amount = bcadd((string) $discount->max_discount, '0', 2);
        }
        // Her satırda en az 0,01 ₺ kalır.
        $ceiling = bcsub($base, bcmul('0.01', (string) count($lines), 2), 2);
        if (bccomp($amount, $ceiling, 2) > 0) {
            $amount = bccomp($ceiling, '0', 2) > 0 ? $ceiling : '0.00';
        }

        // Paylar tüm sipariş verilebilir satırlar için (kapsam dışı satırlara 0).
        $shares = array_fill(0, count($summary->orderable()), '0.00');
        foreach (array_combine(array_keys($eligible), $this->allocate($lines, $base, $amount)) as $index => $share) {
            $shares[$index] = $share;
        }

        return new AppliedDiscount($discount, $amount, $discount->type === 'free_shipping', $shares, $base, $code ?? $discount->code);
    }

    /**
     * İndirimi satır tutarlarıyla orantılı paylaştırır (yarım yukarı yuvarlanır); yuvarlama farkı en büyük satırlardan
     * düzeltilir. Her satırda en az 0,01 ₺ kalır.
     *
     * @param  list<CartLine>  $lines
     * @return list<string> satır sırasına göre indirim payı
     */
    private function allocate(array $lines, string $base, string $amount): array
    {
        $shares = [];
        $room = [];
        $given = '0.00';
        foreach ($lines as $i => $line) {
            $room[$i] = bcsub($line->total(), '0.01', 2);
            $share = bccomp($base, '0', 2) > 0 ? Decimal::round(bcdiv(bcmul($amount, $line->total(), 6), $base, 6), 2) : '0.00';
            $shares[$i] = bccomp($share, $room[$i], 2) > 0 ? $room[$i] : $share;
            $given = bcadd($given, $shares[$i], 2);
        }

        $left = bcsub($amount, $given, 2);
        $order = array_keys($lines);
        usort($order, fn ($a, $b) => bccomp($lines[$b]->total(), $lines[$a]->total(), 2));
        foreach ($order as $i) {
            if (bccomp($left, '0', 2) === 0) {
                break;
            }
            // Fazla verildiyse (eksi) satırın payından düşülür, eksik verildiyse boş yerine eklenir.
            $limit = bccomp($left, '0', 2) > 0 ? bcsub($room[$i], $shares[$i], 2) : bcsub('0', $shares[$i], 2);
            $step = bccomp($left, '0', 2) > 0 ? (bccomp($limit, $left, 2) < 0 ? $limit : $left) : (bccomp($limit, $left, 2) > 0 ? $limit : $left);
            $shares[$i] = bcadd($shares[$i], $step, 2);
            $left = bcsub($left, $step, 2);
        }

        return array_values($shares);
    }
}
