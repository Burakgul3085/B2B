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
        public readonly string $base = '0.00', // kapsamdaki ürünlerin toplamı
        public readonly ?string $code = null,  // kullanılan kod (toplu kodlarda kodun kendisi)
    ) {}

    public function label(): string
    {
        return $this->discount->isCoupon() ? 'Kupon ('.$this->code.')' : $this->discount->name;
    }
}
