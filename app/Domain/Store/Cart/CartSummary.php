<?php

namespace App\Domain\Store\Cart;

/** Sepet özeti: satırlar, KDV dahil ara toplam ve KDV dökümü. Yalnızca sipariş verilebilir satırlar toplama girer. */
final class CartSummary
{
    /** @param  list<CartLine>  $lines */
    public function __construct(public readonly array $lines) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function count(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->quantity(), $this->lines));
    }

    /** @return list<CartLine> */
    public function orderable(): array
    {
        return array_values(array_filter($this->lines, fn (CartLine $l) => $l->isOrderable()));
    }

    public function hasIssues(): bool
    {
        return count($this->orderable()) !== count($this->lines);
    }

    /** KDV dahil ara toplam. */
    public function subtotal(): string
    {
        return array_reduce($this->orderable(), fn (string $sum, CartLine $l) => bcadd($sum, $l->total(), 2), '0.00');
    }

    /** @return array<string, string> KDV oranı (ör. "20") => tutar */
    public function vatBreakdown(): array
    {
        $out = [];
        foreach ($this->orderable() as $line) {
            $rate = (string) (int) $line->variant->vat_rate;
            $out[$rate] = bcadd($out[$rate] ?? '0.00', $line->vat(), 2);
        }
        ksort($out, SORT_NUMERIC);

        return $out;
    }
}
