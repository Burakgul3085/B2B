<?php

namespace App\Domain\Support;

/** Kullanıcı ve dosya girdisindeki ondalık sayıları güvenli biçimde stringe çevirir (float hatası olmadan). */
final class Decimal
{
    /**
     * "1.234,56", "1234,56", "1234.56", 12.5 → "1234.56" (scale basamaklı). Geçersizse null.
     * Hem nokta hem virgül varsa sondaki ondalık ayırıcıdır.
     */
    public static function parse(mixed $value, int $scale): ?string
    {
        if (is_int($value)) {
            return bcadd((string) $value, '0', $scale);
        }
        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }
            $value = rtrim(rtrim(number_format($value, $scale + 2, '.', ''), '0'), '.');
        }
        if (! is_string($value)) {
            return null;
        }

        $value = str_replace([' ', "\u{00A0}", '₺', 'TL'], '', trim($value));
        if ($value === '') {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif ($lastComma !== false) {
            $value = str_replace(',', '', $value);
        }

        if (! preg_match('/^-?\d{1,12}(\.\d+)?$/', $value)) {
            return null;
        }

        // Fazla basamak yuvarlanmaz, reddedilir: kullanıcıya sessizce farklı değer yazılmasın.
        if (str_contains($value, '.') && strlen(explode('.', $value)[1]) > $scale) {
            return null;
        }

        return bcadd($value, '0', $scale);
    }
}
