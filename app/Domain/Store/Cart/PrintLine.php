<?php

namespace App\Domain\Store\Cart;

use App\Domain\Support\Decimal;
use App\Models\Store\StoreCartItem;
use App\Models\Store\StorePrintJob;

/**
 * Sepetteki baskı işi satırı. Fiyat her gösterimde güncel ayarlarla yeniden hesaplanır (PrintCart::refresh).
 * Belge silinmiş / hazır değilse veya fotokopi kapatıldıysa satır işaretlenir ve toplama girmez.
 */
final class PrintLine
{
    public function __construct(
        public readonly StoreCartItem $item,
        public readonly StorePrintJob $job,
        public readonly ?string $issue, // null | 'unavailable'
    ) {}

    public function quantity(): int
    {
        return 1;
    }

    /** KDV dahil (tüm kopyalar). */
    public function total(): string
    {
        return bcadd((string) $this->job->price, '0', 2);
    }

    public function vatRate(): string
    {
        return (string) ($this->job->breakdown['vat_rate'] ?? '20');
    }

    public function vat(): string
    {
        $rate = $this->vatRate();

        return Decimal::round(bcdiv(bcmul($this->total(), $rate, 6), bcadd('100', $rate, 6), 6), 2);
    }

    public function name(): string
    {
        return 'Fotokopi: '.$this->job->file->original_name;
    }

    public function isOrderable(): bool
    {
        return $this->issue === null;
    }
}
