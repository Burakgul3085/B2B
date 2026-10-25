<?php

namespace App\Domain\Store\Discounts;

use App\Domain\Store\Support\PerRequest;
use App\Models\Store\StoreDiscount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Vitrindeki Kampanyalar sayfası ve üst menü sekmesi: yayında olan, "Kampanyalar sayfasında göster" işaretli,
 * kullanım sınırı dolmamış kampanya ve kuponlar. Toplu kodlu kuponların kodu gizli olduğu için gösterilmez.
 */
final class CampaignPage
{
    /** @return Collection<int, StoreDiscount> bitişi yakın olan önce */
    public static function visible(): Collection
    {
        return PerRequest::remember('store.campaign_page', fn () => StoreDiscount::query()->live()
            ->where('show_on_page', true)->where('codes_only', false)
            ->withCount(['redemptions as used_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByRaw('ends_at IS NULL')->orderBy('ends_at')->orderByDesc('id')->get()
            ->reject(fn (StoreDiscount $d) => $d->usage_limit !== null && $d->used_count >= $d->usage_limit)
            ->values());
    }

    public static function any(): bool
    {
        return self::visible()->isNotEmpty();
    }

    public static function find(int $id): ?StoreDiscount
    {
        return self::visible()->firstWhere('id', $id);
    }

    /** Kampanyanın kapsadığı ürünler (kapsamsız kampanya bütün ürünleri kapsar). */
    public static function products(Builder $query, StoreDiscount $discount): Builder
    {
        if (! $discount->isScoped()) {
            return $query;
        }
        $categories = $discount->category_ids ? DiscountService::withDescendants(array_map('intval', $discount->category_ids)) : [];

        return $query->where(fn ($q) => $q->whereIn('store_products.id', array_map('intval', $discount->product_ids ?? []))
            ->when($categories, fn ($q) => $q->orWhereIn('category_id', $categories)));
    }
}
