<?php

namespace App\Domain\Support;

use Illuminate\Support\Carbon;

/**
 * Türkiye saati. Uygulama ve veritabanı zaman damgaları UTC tutulur; "bugün", "bu ay" gibi takvim sınırları ise
 * Türkiye saatine göredir. Sınır, zaman damgası sütunuyla karşılaştırılacaksa UTC'ye çevrilir (Laravel sorguya
 * Carbon'u saat dilimini çevirmeden yazar). Türkiye'de yaz saati yoktur (sabit +03).
 */
final class LocalTime
{
    public const TZ = 'Europe/Istanbul';

    public static function now(): Carbon
    {
        return now(self::TZ);
    }

    /** Türkiye saatine göre bugünün tarihi (Y-m-d): tarih sütunları ve doğrulama kuralları için. */
    public static function today(): string
    {
        return now(self::TZ)->toDateString();
    }

    /** Türkiye saatindeki bir anı zaman damgası sütunlarıyla karşılaştırmak için UTC'ye çevirir. */
    public static function utc(Carbon $local): Carbon
    {
        return $local->copy()->utc();
    }

    /** Y-m-d gün aralığının (iki uç dahil) UTC sınırları. @return array{0: Carbon, 1: Carbon} */
    public static function dayRange(string $from, string $to): array
    {
        return [Carbon::parse($from, self::TZ)->startOfDay()->utc(), Carbon::parse($to, self::TZ)->endOfDay()->utc()];
    }

    /** UTC zaman damgası sütununun Türkiye saatindeki karşılığı (SQL; DATE_FORMAT ile gün/ay kovası için). */
    public static function sql(string $column): string
    {
        return "DATE_ADD({$column}, INTERVAL 3 HOUR)";
    }
}
