<?php

namespace App\Domain\Ordering;

use App\Domain\Support\Decimal;

/**
 * Satır ve sipariş tutarları (docs/03-is-kurallari.md §4.2). Tek yuvarlama kuralı: satır bazında, yarım yukarı.
 *   ara toplam = yuvarla(birim net × miktar, 2)
 *   indirim    = yuvarla(ara toplam × indirim% / 100, 2)
 *   net        = ara toplam − indirim
 *   KDV        = yuvarla(net × KDV% / 100, 2)
 *   toplam     = net + KDV
 * Sipariş toplamları satırların toplamıdır; toplam üzerinden ayrıca yuvarlama yapılmaz.
 */
final class LineCalculator
{
    /** @return array{line_subtotal: string, line_discount: string, line_net: string, line_vat: string, line_total: string} */
    public static function line(string $unitPrice, string $quantity, string $discountPercent, string $vatRate): array
    {
        $subtotal = Decimal::round(bcmul($unitPrice, $quantity, 10), 2);
        $discount = Decimal::round(bcdiv(bcmul($subtotal, $discountPercent, 10), '100', 10), 2);
        $net = bcsub($subtotal, $discount, 2);
        $vat = Decimal::round(bcdiv(bcmul($net, $vatRate, 10), '100', 10), 2);

        return [
            'line_subtotal' => $subtotal,
            'line_discount' => $discount,
            'line_net' => $net,
            'line_vat' => $vat,
            'line_total' => bcadd($net, $vat, 2),
        ];
    }

    /**
     * @param  iterable<array{line_subtotal: string, line_discount: string, line_net: string, line_vat: string, line_total: string}>  $lines
     * @return array{subtotal: string, discount_total: string, net_total: string, vat_total: string, grand_total: string}
     */
    public static function totals(iterable $lines): array
    {
        $t = ['subtotal' => '0.00', 'discount_total' => '0.00', 'net_total' => '0.00', 'vat_total' => '0.00', 'grand_total' => '0.00'];

        foreach ($lines as $l) {
            $t['subtotal'] = bcadd($t['subtotal'], (string) $l['line_subtotal'], 2);
            $t['discount_total'] = bcadd($t['discount_total'], (string) $l['line_discount'], 2);
            $t['net_total'] = bcadd($t['net_total'], (string) $l['line_net'], 2);
            $t['vat_total'] = bcadd($t['vat_total'], (string) $l['line_vat'], 2);
            $t['grand_total'] = bcadd($t['grand_total'], (string) $l['line_total'], 2);
        }

        return $t;
    }
}
