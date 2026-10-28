<?php

namespace App\Domain\Store\Cart;

/**
 * Sepet özeti: ürün satırları ve baskı işleri (fotokopi), KDV dahil ara toplam ve KDV dökümü. Yalnızca sipariş
 * verilebilir satırlar toplama girer. orderable() yalnızca ürün satırlarıdır (kampanya ve kuponlar ürünlere uygulanır);
 * baskı işleri printsOrderable() ile ayrıca gelir.
 */
final class CartSummary
{
    /**
     * @param  list<CartLine>  $lines
     * @param  list<PrintLine>  $prints
     */
    public function __construct(public readonly array $lines, public readonly array $prints = []) {}

    public function isEmpty(): bool
    {
        return $this->lines === [] && $this->prints === [];
    }

    public function count(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->quantity(), $this->lines)) + count($this->prints);
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

    public function hasIssues(): bool
    {
        return count($this->orderable()) !== count($this->lines) || count($this->printsOrderable()) !== count($this->prints);
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

    /** KDV dahil ara toplam (ürünler + baskı). */
    public function subtotal(): string
    {
        return bcadd($this->productsSubtotal(), $this->printsSubtotal(), 2);
    }

    /** @return array<string, string> KDV oranı (ör. "20") => tutar */
    public function vatBreakdown(): array
    {
        $out = [];
        foreach ($this->orderable() as $line) {
            $rate = (string) (int) $line->variant->vat_rate;
            $out[$rate] = bcadd($out[$rate] ?? '0.00', $line->vat(), 2);
        }
        foreach ($this->printsOrderable() as $line) {
            $rate = (string) (int) $line->vatRate();
            $out[$rate] = bcadd($out[$rate] ?? '0.00', $line->vat(), 2);
        }
        ksort($out, SORT_NUMERIC);

        return $out;
    }
}
