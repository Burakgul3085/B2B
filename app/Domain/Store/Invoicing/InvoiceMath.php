<?php

namespace App\Domain\Store\Invoicing;

use App\Domain\Support\Decimal;

/**
 * Fatura kalemi ve toplam hesabı (yalnızca sunucuda). Fiyatlar mağazadaki gibi KDV dahil girilir:
 *   brüt  = miktar × birim fiyat (KDV dahil)          → 2 basamağa yuvarlanır
 *   tutar = brüt − indirim                             (KDV dahil satır tutarı)
 *   net   = tutar × 100 / (100 + KDV oranı)            → 2 basamak (KDV hariç)
 *   KDV   = tutar − net
 * Böylece satırların KDV dahil toplamı, girilen fiyatlarla birebir aynı çıkar.
 */
final class InvoiceMath
{
    public const VAT_RATES = ['20', '10', '1', '0'];

    /** @return array{net_amount: string, vat_amount: string, line_total: string, gross: string} */
    public static function line(string $quantity, string $unitPrice, string $discount, string $vatRate): array
    {
        $gross = Decimal::round(bcmul($quantity, $unitPrice, 6), 2);
        $total = bcsub($gross, $discount, 2);
        $net = Decimal::round(bcdiv(bcmul($total, '100', 6), bcadd('100', $vatRate, 6), 6), 2);

        return ['net_amount' => $net, 'vat_amount' => bcsub($total, $net, 2), 'line_total' => $total, 'gross' => $gross];
    }

    /** KDV hariç birim fiyat (UBL "Price"); 6 basamak. */
    public static function netUnit(string $unitPrice, string $vatRate): string
    {
        return bcdiv(bcmul($unitPrice, '100', 8), bcadd('100', $vatRate, 8), 6);
    }

    /**
     * @param  iterable<array{net_amount: string, vat_amount: string, line_total: string, vat_rate: string}>  $lines
     * @return array{subtotal: string, vat_total: string, total: string, by_rate: array<string, array{net: string, vat: string}>}
     */
    public static function totals(iterable $lines): array
    {
        $subtotal = $vat = $total = '0.00';
        $byRate = [];
        foreach ($lines as $l) {
            $subtotal = bcadd($subtotal, (string) $l['net_amount'], 2);
            $vat = bcadd($vat, (string) $l['vat_amount'], 2);
            $total = bcadd($total, (string) $l['line_total'], 2);
            $rate = rtrim(rtrim(number_format((float) $l['vat_rate'], 2, '.', ''), '0'), '.');
            $byRate[$rate] ??= ['net' => '0.00', 'vat' => '0.00'];
            $byRate[$rate]['net'] = bcadd($byRate[$rate]['net'], (string) $l['net_amount'], 2);
            $byRate[$rate]['vat'] = bcadd($byRate[$rate]['vat'], (string) $l['vat_amount'], 2);
        }
        krsort($byRate, SORT_NUMERIC);

        return ['subtotal' => $subtotal, 'vat_total' => $vat, 'total' => $total, 'by_rate' => $byRate];
    }
}
