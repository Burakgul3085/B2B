<?php

namespace App\Domain\Support\Payments;

/** TR IBAN doğrulama (mod 97) ve gösterim; mağaza ve B2B ortaktır. */
final class Iban
{
    /** Boşluklu/küçük harfli yazımı düzeltir; geçerli TR IBAN değilse null. */
    public static function normalize(?string $raw): ?string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', (string) $raw));
        if (! preg_match('/^TR\d{24}$/', $iban)) {
            return null;
        }
        $numeric = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $char) {
            $numeric .= ctype_digit($char) ? $char : (string) (ord($char) - 55);
        }

        return bcmod($numeric, '97') === '1' ? $iban : null;
    }

    /** TR00 0000 0000 ... biçiminde gösterim. */
    public static function format(string $iban): string
    {
        return trim(chunk_split($iban, 4, ' '));
    }
}
