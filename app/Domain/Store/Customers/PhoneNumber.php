<?php

namespace App\Domain\Store\Customers;

/** Türkiye cep telefonu: "0532 123 45 67", "+90 532…", "532…" → "05321234567". */
final class PhoneNumber
{
    public const PATTERN = '/^05\d{9}$/';

    public static function normalize(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '5')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /** "05321234567" → "0532 123 45 67" */
    public static function format(?string $phone): string
    {
        if ($phone === null || ! preg_match(self::PATTERN, $phone)) {
            return (string) $phone;
        }

        return substr($phone, 0, 4).' '.substr($phone, 4, 3).' '.substr($phone, 7, 2).' '.substr($phone, 9, 2);
    }
}
