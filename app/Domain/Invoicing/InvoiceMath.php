<?php

namespace App\Domain\Invoicing;

use App\Domain\Support\Decimal;

/**
 * B2B fatura tutarları. B2B siparişte olduğu gibi KDV hariç çalışır (Ordering\LineCalculator ile aynı kural):
 *   ara toplam = yuvarla(miktar × KDV hariç birim fiyat, 2)
 *   net        = ara toplam − iskonto (fiyat listesi indirimi + kampanya payı)
 *   KDV        = yuvarla(net × KDV% / 100, 2)
 *   toplam     = net + KDV
 * Fatura kalemleri siparişin hesaplanmış tutarlarını taşır; bu sınıf yalnızca denetim ve teslimat ücreti içindir.
 */
final class InvoiceMath
{
    /** @return array{net_amount: string, vat_amount: string, line_total: string, subtotal: string} */
    public static function line(string $quantity, string $unitPrice, string $discount, string $vatRate): array
    {
        $subtotal = Decimal::round(bcmul($quantity, $unitPrice, 10), 2);
        $net = bcsub($subtotal, bcadd($discount, '0', 2), 2);
        $vat = Decimal::round(bcdiv(bcmul($net, $vatRate, 10), '100', 10), 2);

        return ['net_amount' => $net, 'vat_amount' => $vat, 'line_total' => bcadd($net, $vat, 2), 'subtotal' => $subtotal];
    }

    /**
     * KDV dahil tutarı (teslimat ücreti) KDV hariç ve KDV'ye ayırır; toplam girilen tutarla birebir aynı kalır.
     *
     * @return array{net_amount: string, vat_amount: string, line_total: string}
     */
    public static function fromGross(string $gross, string $vatRate): array
    {
        $gross = bcadd($gross, '0', 2);
        $net = Decimal::round(bcdiv(bcmul($gross, '100', 10), bcadd('100', $vatRate, 10), 10), 2);

        return ['net_amount' => $net, 'vat_amount' => bcsub($gross, $net, 2), 'line_total' => $gross];
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
            $rate = self::rate((string) $l['vat_rate']);
            $byRate[$rate] ??= ['net' => '0.00', 'vat' => '0.00'];
            $byRate[$rate]['net'] = bcadd($byRate[$rate]['net'], (string) $l['net_amount'], 2);
            $byRate[$rate]['vat'] = bcadd($byRate[$rate]['vat'], (string) $l['vat_amount'], 2);
        }
        krsort($byRate, SORT_NUMERIC);

        return ['subtotal' => $subtotal, 'vat_total' => $vat, 'total' => $total, 'by_rate' => $byRate];
    }

    /** "20.00" → "20", "1.50" → "1.5" */
    public static function rate(string $rate): string
    {
        return rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');
    }
}
