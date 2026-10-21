<?php

namespace App\Domain\Store\Discounts;

use App\Models\Store\StoreDiscount;

/** Sepete uygulanan indirim: ürünlerden düşülen tutar, satır payları ve ücretsiz teslimat. */
final class AppliedDiscount
{
    /** @param  list<string>  $shares  sipariş verilebilir satırların sırasına göre indirim payı */
    public function __construct(
        public readonly StoreDiscount $discount,
        public readonly string $amount,
        public readonly bool $freeShipping,
        public readonly array $shares,
    ) {}

    public function label(): string
    {
        return $this->discount->isCoupon() ? 'Kupon ('.$this->discount->code.')' : $this->discount->name;
    }
}
