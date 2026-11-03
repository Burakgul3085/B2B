<?php

namespace App\Domain\Pricing;

use App\Models\Promotion;

/** Sepete uygulanan kupon / kampanya: toplam indirim (KDV hariç) ve satır anahtarı → pay. */
final class AppliedPromotion
{
    /** @param  array<int, string>  $shares  yalnızca kapsamdaki satırlar */
    public function __construct(
        public readonly Promotion $promotion,
        public readonly string $amount,
        public readonly array $shares,
    ) {}
}
