<?php

namespace App\Domain\Store\Cart;

use App\Domain\Support\Decimal;
use App\Models\Store\StoreCartItem;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;

/** Sepet satırı: güncel fiyatla hesaplanır. Satışta olmayan veya stoğu yetmeyen satır işaretlenir ve toplama girmez. */
final class CartLine
{
    public readonly StoreProductVariant $variant;

    public readonly StoreProduct $product;

    /** null | 'unavailable' (satışta değil / tükendi) | 'insufficient' (adet stoktan fazla) */
    public readonly ?string $issue;

    public function __construct(public readonly StoreCartItem $item, public readonly int $available)
    {
        $this->variant = $item->variant;
        $this->product = $this->variant->product;
        $this->issue = match (true) {
            ! $this->variant->is_active || ! $this->product->is_published || $available <= 0 => 'unavailable',
            $item->quantity > $available => 'insufficient',
            default => null,
        };
    }

    public function quantity(): int
    {
        return $this->item->quantity;
    }

    /** KDV dahil birim fiyat. */
    public function unitPrice(): string
    {
        return bcadd((string) $this->variant->price, '0', 2);
    }

    /** KDV dahil satır toplamı. */
    public function total(): string
    {
        return bcmul($this->unitPrice(), (string) $this->quantity(), 2);
    }

    /** Satır toplamının içindeki KDV: toplam × oran / (100 + oran), yarım yukarı yuvarlanır. */
    public function vat(): string
    {
        $rate = (string) $this->variant->vat_rate;

        return Decimal::round(bcdiv(bcmul($this->total(), $rate, 6), bcadd('100', $rate, 6), 6), 2);
    }

    public function isOrderable(): bool
    {
        return $this->issue === null;
    }
}
