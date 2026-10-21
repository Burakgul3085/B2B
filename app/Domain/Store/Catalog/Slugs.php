<?php

namespace App\Domain\Store\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Mağaza adresleri için benzersiz, Türkçe karakterden arındırılmış kısa ad (ör. "Jel Kalem Mavi" → jel-kalem-mavi). */
final class Slugs
{
    /** @param  class-string<Model>  $model */
    public static function unique(string $model, string $source, ?int $exceptId = null, int $max = 200): string
    {
        $base = Str::limit(Str::slug(Str::ascii(self::turkish($source))), $max - 6, '') ?: 'kayit';
        $base = rtrim($base, '-');

        $slug = $base;
        for ($i = 2; $model::where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /** Kullanıcının yazdığı kısa adı biçimlendirir; boşsa null. */
    public static function normalize(?string $value): ?string
    {
        $slug = Str::slug(Str::ascii(self::turkish((string) $value)));

        return $slug === '' ? null : $slug;
    }

    private static function turkish(string $value): string
    {
        return strtr($value, ['ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g', 'ç' => 'c', 'Ç' => 'c', 'ö' => 'o', 'Ö' => 'o', 'ü' => 'u', 'Ü' => 'u']);
    }
}
