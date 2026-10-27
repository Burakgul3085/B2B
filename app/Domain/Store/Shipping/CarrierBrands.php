<?php

namespace App\Domain\Store\Shipping;

/**
 * Bilinen kargo firmaları: panelde yazılan firma adından tanınır; logo ve takip bağlantısı kendiliğinden gelir.
 * Takip bağlantısında {takip} varsa firma sayfası numarayla doğrudan sorgular. Yoksa firma numarayı adresten
 * almıyor demektir (doğrulama kodu ister); bağlantı takip sayfasını açar, müşteri numarayı kopyalayıp yapıştırır.
 * Bağlantılar 2026-10-27'de denendi. Logolar public/images/carriers altındadır (kaynaklar: KAYNAKLAR.md).
 */
class CarrierBrands
{
    /** anahtar => [görünen ad, takip bağlantısı, adlar (sadeleştirilmiş)] — sıra önemli: "dhl ecommerce" MNG'dir. */
    public const BRANDS = [
        'aras' => ['Aras Kargo', 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code={takip}', ['aras']],
        'yurtici' => ['Yurtiçi Kargo', 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code={takip}', ['yurtici']],
        'mng' => ['MNG Kargo', 'https://www.dhlecommerce.com.tr/gonderitakip', ['mng', 'dhlecommerce', 'dhlecom']],
        'surat' => ['Sürat Kargo', 'https://suratkargo.com.tr/KargoTakip/', ['surat']],
        'ptt' => ['PTT Kargo', 'https://www.ptt.gov.tr/', ['ptt']],
        'dhl' => ['DHL Express', 'https://www.dhl.com/tr-tr/home/tracking.html?submit=1&tracking-id={takip}', ['dhl']],
        'ups' => ['UPS', 'https://www.ups.com/track?loc=tr_TR&tracknum={takip}', ['ups']],
        'kolaygelsin' => ['Kolay Gelsin', 'https://www.kolaygelsin.com/tracking', ['kolaygelsin']],
    ];

    /** Firma adından bilinen firma. @return array{key: string, name: string, tracking_url: string, logo: string}|null */
    public static function match(?string $name): ?array
    {
        $plain = self::plain((string) $name);
        if ($plain === '') {
            return null;
        }
        foreach (self::BRANDS as $key => [$label, $url, $aliases]) {
            foreach ($aliases as $alias) {
                if (str_contains($plain, $alias)) {
                    return ['key' => $key, 'name' => $label, 'tracking_url' => $url, 'logo' => asset("images/carriers/{$key}.png")];
                }
            }
        }

        return null;
    }

    /** "Yurtiçi Kargo" → "yurticikargo": Türkçe harfler sadeleşir, boşluk ve işaretler atılır. */
    private static function plain(string $name): string
    {
        $name = mb_strtolower(strtr($name, ['İ' => 'i', 'I' => 'ı']), 'UTF-8');

        return preg_replace('/[^a-z0-9]/', '', strtr($name, ['ı' => 'i', 'ç' => 'c', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o']));
    }
}
