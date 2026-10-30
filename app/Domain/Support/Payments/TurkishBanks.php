<?php

namespace App\Domain\Support\Payments;

/**
 * Türkiye'deki bankalar: TR IBAN'ın 5–9. hanelerindeki banka (EFT) kodundan banka adı ve logosu bulunur.
 * Mağaza ve B2B havale hesaplarında ortaktır. Yönetici yalnızca IBAN ve hesap sahibini girer; banka kendiliğinden tanınır, logosu gösterilir.
 * Listede olmayan bankada ad elle yazılır ve logo yerine baş harfli rozet gösterilir.
 * Logolar public/images/banks altındadır (kaynaklar: KAYNAKLAR.md).
 */
class TurkishBanks
{
    /** Banka kodu => [ad, logo dosyası] */
    public const BANKS = [
        '00010' => ['Ziraat Bankası', 'ziraat'],
        '00012' => ['Halkbank', 'halkbank'],
        '00015' => ['VakıfBank', 'vakifbank'],
        '00032' => ['TEB', 'teb'],
        '00046' => ['Akbank', 'akbank'],
        '00059' => ['Şekerbank', 'sekerbank'],
        '00062' => ['Garanti BBVA', 'garanti'],
        '00064' => ['Türkiye İş Bankası', 'isbank'],
        '00067' => ['Yapı Kredi', 'yapikredi'],
        '00096' => ['Turkish Bank', 'turkishbank'],
        '00099' => ['ING', 'ing'],
        '00103' => ['Fibabanka', 'fibabanka'],
        '00111' => ['QNB', 'qnb'],
        '00123' => ['HSBC', 'hsbc'],
        '00124' => ['Alternatif Bank', 'alternatifbank'],
        '00125' => ['Burgan Bank', 'burgan'],
        '00134' => ['DenizBank', 'denizbank'],
        '00135' => ['Anadolubank', 'anadolubank'],
        '00143' => ['Aktif Bank', 'aktifbank'],
        '00146' => ['Odeabank', 'odeabank'],
        '00203' => ['Albaraka Türk', 'albaraka'],
        '00205' => ['Kuveyt Türk', 'kuveytturk'],
        '00206' => ['Türkiye Finans', 'turkiyefinans'],
        '00209' => ['Ziraat Katılım', 'ziraatkatilim'],
        '00210' => ['Vakıf Katılım', 'vakifkatilim'],
        '00211' => ['Emlak Katılım', 'emlakkatilim'],
    ];

    /** IBAN'dan banka (boşluklu/küçük harfli yazım da olur). @return array{code: string, name: string, logo: string}|null */
    public static function fromIban(?string $iban): ?array
    {
        $iban = strtoupper(preg_replace('/\s+/', '', (string) $iban));
        if (! preg_match('/^TR\d{7}/', $iban)) {
            return null;
        }
        $code = substr($iban, 4, 5);
        if (! isset(self::BANKS[$code])) {
            return null;
        }
        [$name, $file] = self::BANKS[$code];

        return ['code' => $code, 'name' => $name, 'logo' => asset("images/banks/{$file}.png")];
    }

    /** Yönetim formundaki canlı tanıma için: kod => [ad, logo adresi]. */
    public static function forBrowser(): array
    {
        return array_map(fn ($b) => ['name' => $b[0], 'logo' => asset("images/banks/{$b[1]}.png")], self::BANKS);
    }
}
