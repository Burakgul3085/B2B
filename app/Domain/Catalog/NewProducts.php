<?php

namespace App\Domain\Catalog;

use App\Domain\Ordering\CartService;
use App\Domain\Support\LocalTime;
use App\Models\BusinessSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Yeni ürün": son N gün içinde eklenen ürün (işletme ayarı, varsayılan 30). Toptancı bir başlangıç tarihi
 * verdiyse o tarihten önce eklenenler hiç yeni sayılmaz (ilk toplu yüklemedeki ürünler "yeni" görünmesin).
 */
final class NewProducts
{
    public const DAY_OPTIONS = [7, 15, 30, 60, 90];

    public static function since(?BusinessSetting $settings): CarbonImmutable
    {
        $window = CarbonImmutable::now(LocalTime::TZ)->subDays((int) ($settings?->new_product_days ?: 30))->startOfDay()->utc();
        $floor = $settings?->new_products_since ? CarbonImmutable::parse($settings->new_products_since, LocalTime::TZ)->startOfDay()->utc() : null;

        return $floor && $floor->greaterThan($window) ? $floor : $window;
    }

    public static function apply(Builder $query, ?BusinessSetting $settings): Builder
    {
        return $query->where('products.created_at', '>=', self::since($settings));
    }

    /** Bayinin sipariş verebileceği yeni ürün sayısı (menü rozeti). */
    public static function count(?BusinessSetting $settings): int
    {
        return self::apply(CartService::orderableProducts(), $settings)->count();
    }

    public static function isNew($product, ?BusinessSetting $settings): bool
    {
        return $product->created_at !== null && $product->created_at->greaterThanOrEqualTo(self::since($settings));
    }
}
