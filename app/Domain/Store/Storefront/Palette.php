<?php

namespace App\Domain\Store\Storefront;

/**
 * Vitrin renk kimliği: lacivert zemin ve logodaki dört yapboz rengi. Renkler CSS token'larıdır (resources/css/app.css,
 * koyu temada açık tonlara döner); burada yalnızca sınıf adları tutulur. Sınıflar tam yazılır ki Tailwind derlesin.
 */
class Palette
{
    public const COLORS = ['orange' => 'Turuncu', 'green' => 'Yeşil', 'blue' => 'Mavi', 'yellow' => 'Sarı', 'navy' => 'Lacivert'];

    /** Rengi seçilmemiş ana kategoriler bu sırayla renk alır. */
    public const CYCLE = ['orange', 'green', 'blue', 'yellow'];

    private const CLASSES = [
        'orange' => ['solid' => 'bg-puzzle-orange', 'soft' => 'bg-puzzle-orange-soft', 'text' => 'text-puzzle-orange', 'ring' => 'ring-puzzle-orange/30', 'border' => 'border-puzzle-orange'],
        'green' => ['solid' => 'bg-puzzle-green', 'soft' => 'bg-puzzle-green-soft', 'text' => 'text-puzzle-green', 'ring' => 'ring-puzzle-green/30', 'border' => 'border-puzzle-green'],
        'blue' => ['solid' => 'bg-puzzle-blue', 'soft' => 'bg-puzzle-blue-soft', 'text' => 'text-puzzle-blue', 'ring' => 'ring-puzzle-blue/30', 'border' => 'border-puzzle-blue'],
        'yellow' => ['solid' => 'bg-puzzle-yellow', 'soft' => 'bg-puzzle-yellow-soft', 'text' => 'text-puzzle-yellow', 'ring' => 'ring-puzzle-yellow/30', 'border' => 'border-puzzle-yellow'],
        'navy' => ['solid' => 'bg-navy', 'soft' => 'bg-navy-soft', 'text' => 'text-navy-ink', 'ring' => 'ring-navy/30', 'border' => 'border-navy'],
    ];

    /** @return array{solid: string, soft: string, text: string, ring: string, border: string} */
    public static function classes(?string $color): array
    {
        return self::CLASSES[$color] ?? self::CLASSES['navy'];
    }

    /** Kategorinin rengi: seçilmişse o, değilse sıradaki yapboz rengi. */
    public static function forIndex(?string $chosen, int $index): string
    {
        return isset(self::CLASSES[$chosen]) ? $chosen : self::CYCLE[$index % count(self::CYCLE)];
    }
}
