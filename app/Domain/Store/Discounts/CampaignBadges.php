<?php

namespace App\Domain\Store\Discounts;

use App\Domain\Store\Support\PerRequest;
use App\Domain\Support\Money;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreProduct;

/**
 * Ürün kartı ve ürün sayfasındaki "Sepette %10 indirim" rozeti. Fiyat değişmez; indirim sepette DiscountService ile
 * hesaplanır. Yalnızca herkese açık, tutar koşulu olmayan yüzde/tutar kampanyaları rozet verir (koşullu kampanyalar
 * sepette "şu kadar daha ekleyin" uyarısıyla gösterilir).
 */
final class CampaignBadges
{
    /** @return list<array{discount: StoreDiscount, categories: list<int>, products: list<int>}> */
    private static function campaigns(): array
    {
        return PerRequest::remember('store.campaign_badges', fn () => StoreDiscount::query()->live()->whereNull('code')
            ->where('audience', 'all')->whereNull('min_subtotal')->whereIn('type', ['percent', 'fixed'])->orderBy('id')->get()
            ->map(fn (StoreDiscount $d) => [
                'discount' => $d,
                'categories' => $d->category_ids ? DiscountService::withDescendants(array_map('intval', $d->category_ids)) : [],
                'products' => array_map('intval', $d->product_ids ?? []),
            ])->all());
    }

    /** Ürünü kapsayan en avantajlı kampanyanın rozet metni; yoksa null. */
    public static function for(StoreProduct $product, ?string $price = null): ?string
    {
        $best = self::best($product, $price);

        return $best ? self::label($best) : null;
    }

    public static function label(StoreDiscount $discount): string
    {
        return 'Sepette '.($discount->type === 'percent' ? '%'.rtrim(rtrim((string) $discount->value, '0'), '.') : Money::format($discount->value)).' indirim';
    }

    /** Ürünü kapsayan en avantajlı rozet kampanyası. */
    public static function best(StoreProduct $product, ?string $price = null): ?StoreDiscount
    {
        $best = null;
        $bestAmount = null;
        foreach (self::campaigns() as ['discount' => $d, 'categories' => $categories, 'products' => $products]) {
            $scoped = $categories || $products;
            if ($scoped && ! in_array($product->id, $products, true) && ! in_array((int) $product->category_id, $categories, true)) {
                continue;
            }
            $amount = $d->type === 'percent'
                ? ($price !== null ? bcdiv(bcmul($price, (string) $d->value, 4), '100', 2) : (string) $d->value)
                : (string) $d->value;
            if ($bestAmount === null || bccomp($amount, $bestAmount, 2) > 0) {
                [$best, $bestAmount] = [$d, $amount];
            }
        }

        return $best;
    }
}
