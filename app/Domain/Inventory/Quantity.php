<?php

namespace App\Domain\Inventory;

use App\Domain\Support\Decimal;
use App\Models\Product;
use DomainException;

/**
 * Miktar yardımcıları. Miktarlar 3 ondalıklı string olarak bcmath ile işlenir; float kullanılmaz.
 */
final class Quantity
{
    public const SCALE = 3;

    /** Kullanıcı girdisini ("1.250,5" / "12,5" / "12.5") normalize eder; geçersizse null. */
    public static function parse(mixed $value): ?string
    {
        return Decimal::parse($value, self::SCALE);
    }

    /** Pozitif ve ürün biriminin izin verdiği biçimde olmalı. */
    public static function assertValidFor(Product $product, string $qty): void
    {
        if (bccomp($qty, '0', self::SCALE) <= 0) {
            throw new DomainException('Miktar sıfırdan büyük olmalıdır.');
        }

        if (! $product->unit->allows_fraction && ! self::isWhole($qty)) {
            throw new DomainException("\"{$product->unit->name}\" biriminde küsuratlı miktar girilemez.");
        }
    }

    public static function isWhole(string $qty): bool
    {
        return bccomp($qty, bcadd($qty, '0', 0), self::SCALE) === 0;
    }

    public static function negate(string $qty): string
    {
        return bcmul($qty, '-1', self::SCALE);
    }

    public static function format(string|float|null $qty): string
    {
        if ($qty === null) {
            return '—';
        }
        $qty = bcadd((string) $qty, '0', self::SCALE);
        $decimals = self::isWhole($qty) ? 0 : self::SCALE;
        $formatted = number_format((float) $qty, $decimals, ',', '.');

        return $decimals ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}
