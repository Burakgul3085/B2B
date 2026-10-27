<?php

namespace App\Domain\Store\Reports;

use App\Domain\Support\Money;
use Illuminate\Support\Carbon;

/** Ekran ve PDF için hücre biçimlendirme; Excel'de sayılar sayı olarak yazılır. */
final class ReportCell
{
    public static function display(string $type, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'money' => Money::format((string) $value),
            'qty', 'int' => number_format((float) $value, 0, ',', '.'),
            'percent' => '%'.rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ','),
            'date' => Carbon::parse($value)->format('d.m.Y'),
            'datetime' => Carbon::parse($value, 'UTC')->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
            default => (string) $value,
        };
    }

    public static function isNumeric(string $type): bool
    {
        return in_array($type, ['money', 'qty', 'int', 'percent'], true);
    }

    /** Excel formül enjeksiyonu önlemi: = + - @ sekme veya satır başıyla başlayan metnin başına ' eklenir. */
    public static function safeText(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
