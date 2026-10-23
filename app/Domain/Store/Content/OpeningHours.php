<?php

namespace App\Domain\Store\Content;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Mağazanın çalışma saatleri: her gün için açılış–kapanış ya da kapalı. Saatler İstanbul saatine göredir.
 * Yapı: ['mon' => ['closed' => false, 'open' => '09:00', 'close' => '19:00'], …]
 */
class OpeningHours
{
    public const DAYS = ['mon' => 'Pazartesi', 'tue' => 'Salı', 'wed' => 'Çarşamba', 'thu' => 'Perşembe', 'fri' => 'Cuma', 'sat' => 'Cumartesi', 'sun' => 'Pazar'];

    private const SCHEMA_DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    /** @return array<string, array{closed: bool, open: string, close: string}> */
    public static function defaults(): array
    {
        $hours = [];
        foreach (array_keys(self::DAYS) as $day) {
            $hours[$day] = ['closed' => $day === 'sun', 'open' => '09:00', 'close' => $day === 'sat' ? '17:00' : '19:00'];
        }

        return $hours;
    }

    /**
     * Formdan gelen saatleri doğrular.
     *
     * @return array{0: array<string, array{closed: bool, open: string, close: string}>, 1: array<string, string>} saatler, hatalar
     */
    public static function fromInput(array $input): array
    {
        $hours = [];
        $errors = [];
        foreach (self::DAYS as $day => $label) {
            $row = (array) ($input[$day] ?? []);
            $closed = filter_var($row['closed'] ?? false, FILTER_VALIDATE_BOOL);
            $open = trim((string) ($row['open'] ?? ''));
            $close = trim((string) ($row['close'] ?? ''));
            if (! $closed && (! self::validTime($open) || ! self::validTime($close) || $open >= $close)) {
                $errors["hours.{$day}"] = "{$label}: açılış ve kapanış saatini girin (kapanış açılıştan sonra olmalı) ya da \"Kapalı\" işaretleyin.";
            }
            $hours[$day] = ['closed' => $closed, 'open' => self::validTime($open) ? $open : '09:00', 'close' => self::validTime($close) ? $close : '19:00'];
        }

        return [$hours, $errors];
    }

    /** Şu an açık mı? Saat bilgisi hiç yoksa null. */
    public static function isOpenAt(array $hours, ?CarbonInterface $at = null): ?bool
    {
        if ($hours === []) {
            return null;
        }
        $at = ($at ?? Carbon::now())->copy()->timezone('Europe/Istanbul');
        $day = $hours[strtolower($at->locale('en')->isoFormat('ddd'))] ?? null;
        if (! $day || $day['closed']) {
            return false;
        }
        $now = $at->format('H:i');

        return $now >= $day['open'] && $now < $day['close'];
    }

    /**
     * Ardışık aynı saatli günleri birleştirir: "Pazartesi – Cuma 09:00 – 19:00".
     *
     * @return list<array{days: string, hours: string, closed: bool}>
     */
    public static function grouped(array $hours): array
    {
        $groups = [];
        foreach (self::DAYS as $day => $label) {
            $row = $hours[$day] ?? ['closed' => true, 'open' => '', 'close' => ''];
            $text = $row['closed'] ? 'Kapalı' : "{$row['open']} – {$row['close']}";
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['hours'] === $text) {
                $groups[$last]['to'] = $label;

                continue;
            }
            $groups[] = ['from' => $label, 'to' => null, 'hours' => $text, 'closed' => $row['closed']];
        }

        return array_map(fn ($g) => ['days' => $g['to'] ? "{$g['from']} – {$g['to']}" : $g['from'], 'hours' => $g['hours'], 'closed' => $g['closed']], $groups);
    }

    /** schema.org openingHoursSpecification (Google'da çalışma saatleri). */
    public static function schema(array $hours): array
    {
        $specs = [];
        foreach ($hours as $day => $row) {
            if (! ($row['closed'] ?? true) && isset(self::SCHEMA_DAYS[$day])) {
                $specs[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => self::SCHEMA_DAYS[$day], 'opens' => $row['open'], 'closes' => $row['close']];
            }
        }

        return $specs;
    }

    private static function validTime(string $value): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
    }
}
