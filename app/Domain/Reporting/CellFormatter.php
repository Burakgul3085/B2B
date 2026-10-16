<?php

namespace App\Domain\Reporting;

use App\Domain\Inventory\Quantity;
use App\Domain\Support\Money;
use Illuminate\Support\Carbon;

/** Ekran ve PDF için hücre biçimlendirme; Excel'de sayılar sayı olarak yazılır. */
final class CellFormatter
{
    public static function display(string $type, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'money' => Money::format((string) $value),
            'qty' => Quantity::format((string) $value),
            'int' => number_format((int) $value, 0, ',', '.'),
            'percent' => '%'.rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ','),
            'date' => Carbon::parse($value)->format('d.m.Y'),
            'datetime' => Carbon::parse($value)->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
            default => (string) $value,
        };
    }

    public static function isNumeric(string $type): bool
    {
        return in_array($type, ['money', 'qty', 'int', 'percent'], true);
    }

    /**
     * Excel/CSV formül enjeksiyonu önlemi: = + - @ sekme veya satır başıyla başlayan METİN hücrelerinin
     * başına tek tırnak eklenir. Sayılar sayı hücresi olarak yazıldığından etkilenmez.
     */
    public static function safeText(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
