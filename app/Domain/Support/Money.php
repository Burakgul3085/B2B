<?php

namespace App\Domain\Support;

/** Para gösterimi. Hesaplar bcmath ile yapılır; bu sınıf yalnızca biçimlendirir. */
final class Money
{
    public static function format(string|float|int|null $amount, bool $symbol = true): string
    {
        if ($amount === null) {
            return '—';
        }

        $formatted = number_format((float) bcadd((string) $amount, '0', 2), 2, ',', '.');

        return $symbol ? '₺'.$formatted : $formatted;
    }

    /** Birim fiyat: 2 ondalık, gerekiyorsa 4 ondalığa kadar gösterir. */
    public static function formatUnit(string|float|int|null $amount): string
    {
        if ($amount === null) {
            return '—';
        }
        $value = bcadd((string) $amount, '0', 4);
        $decimals = bccomp($value, bcadd($value, '0', 2), 4) === 0 ? 2 : 4;

        return '₺'.number_format((float) $value, $decimals, ',', '.');
    }
}
