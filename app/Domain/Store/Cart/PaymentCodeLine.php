<?php

namespace App\Domain\Store\Cart;

use App\Domain\Support\Decimal;
use App\Models\Store\StoreCartItem;
use App\Models\Store\StorePaymentCode;

/**
 * Sepetteki özel ödeme kodu satırı. Tutar ve KDV oranı kodun kaydındandır (yönetici belirler); müşteri değiştiremez.
 * Kod kullanılmış, iptal edilmiş, süresi dolmuş veya kodla ödeme kapatılmışsa satır işaretlenir ve toplama girmez.
 */
final class PaymentCodeLine
{
    public function __construct(
        public readonly StoreCartItem $item,
        public readonly StorePaymentCode $code,
        public readonly string $itemName,
        public readonly ?string $issue, // null | 'unavailable'
    ) {}

    public function quantity(): int
    {
        return 1;
    }

    /** KDV dahil. */
    public function total(): string
    {
        return bcadd((string) $this->code->amount, '0', 2);
    }

    public function vatRate(): string
    {
        return (string) (int) $this->code->vat_rate;
    }

    public function vat(): string
    {
        $rate = $this->vatRate();

        return Decimal::round(bcdiv(bcmul($this->total(), $rate, 6), bcadd('100', $rate, 6), 6), 2);
    }

    public function name(): string
    {
        return $this->itemName.': '.$this->code->title;
    }

    public function isOrderable(): bool
    {
        return $this->issue === null;
    }
}
