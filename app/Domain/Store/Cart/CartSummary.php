<?php

namespace App\Domain\Store\Cart;

/**
 * Sepet özeti: ürün satırları, baskı işleri (fotokopi) ve özel ödeme kodları; KDV dahil ara toplam ve KDV dökümü. Yalnızca
 * sipariş verilebilir satırlar toplama girer. orderable() yalnızca ürün satırlarıdır (kampanya ve kuponlar ürünlere uygulanır);
 * baskı işleri printsOrderable(), ödeme kodları codesOrderable() ile ayrıca gelir.
 */
final class CartSummary
{
    /**
     * @param  list<CartLine>  $lines
     * @param  list<PrintLine>  $prints
     * @param  list<PaymentCodeLine>  $codes
     */
    public function __construct(public readonly array $lines, public readonly array $prints = [], public readonly array $codes = []) {}

    public function isEmpty(): bool
    {
        return $this->lines === [] && $this->prints === [] && $this->codes === [];
    }

    public function count(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->quantity(), $this->lines)) + count($this->prints) + count($this->codes);
    }

    /** @return list<CartLine> */
    public function orderable(): array
    {
        return array_values(array_filter($this->lines, fn (CartLine $l) => $l->isOrderable()));
    }

    /** @return list<PrintLine> */
    public function printsOrderable(): array
    {
        return array_values(array_filter($this->prints, fn (PrintLine $l) => $l->isOrderable()));
    }

    /** @return list<PaymentCodeLine> */
    public function codesOrderable(): array
    {
        return array_values(array_filter($this->codes, fn (PaymentCodeLine $l) => $l->isOrderable()));
    }

    public function hasIssues(): bool
    {
        return count($this->orderable()) !== count($this->lines) || count($this->printsOrderable()) !== count($this->prints)
            || count($this->codesOrderable()) !== count($this->codes);
    }

    /** Ürünlerin KDV dahil toplamı (indirimlerin uygulandığı taban). */
    public function productsSubtotal(): string
    {
        return array_reduce($this->orderable(), fn (string $sum, CartLine $l) => bcadd($sum, $l->total(), 2), '0.00');
    }

    /** Baskı işlerinin KDV dahil toplamı. */
    public function printsSubtotal(): string
    {
        return array_reduce($this->printsOrderable(), fn (string $sum, PrintLine $l) => bcadd($sum, $l->total(), 2), '0.00');
    }

    /** Özel ödeme kodlarının KDV dahil toplamı. */
    public function codesSubtotal(): string
    {
        return array_reduce($this->codesOrderable(), fn (string $sum, PaymentCodeLine $l) => bcadd($sum, $l->total(), 2), '0.00');
    }

    /** KDV dahil ara toplam (ürünler + baskı + ödeme kodları). Ücretsiz kargo sınırı bu tutara (indirim düşülerek) bakar. */
    public function subtotal(): string
    {
        return bcadd(bcadd($this->productsSubtotal(), $this->printsSubtotal(), 2), $this->codesSubtotal(), 2);
    }

    /** @return array<string, string> KDV oranı (ör. "20") => tutar */
    public function vatBreakdown(): array
    {
        $out = [];
        foreach ($this->orderable() as $line) {
            $rate = (string) (int) $line->variant->vat_rate;
            $out[$rate] = bcadd($out[$rate] ?? '0.00', $line->vat(), 2);
        }
        foreach ([...$this->printsOrderable(), ...$this->codesOrderable()] as $line) {
            $rate = (string) (int) $line->vatRate();
            $out[$rate] = bcadd($out[$rate] ?? '0.00', $line->vat(), 2);
        }
        ksort($out, SORT_NUMERIC);

        return $out;
    }
}
