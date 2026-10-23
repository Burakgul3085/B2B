<?php

namespace App\Domain\Store\Storefront;

use Illuminate\Support\Collection;

/** Son bakılan ürünler: oturumda tutulur (yeni çerez gerekmez), en fazla 12 ürün. */
class RecentlyViewed
{
    private const KEY = 'store.recent';

    private const LIMIT = 12;

    public function __construct(private Catalog $catalog) {}

    public function push(int $productId): void
    {
        $ids = array_values(array_diff(session(self::KEY, []), [$productId]));
        array_unshift($ids, $productId);
        session([self::KEY => array_slice($ids, 0, self::LIMIT)]);
    }

    /** Yayındaki ürünler, bakılma sırasıyla. */
    public function products(?int $except = null, int $limit = 8): Collection
    {
        $ids = array_values(array_diff(session(self::KEY, []), [$except]));
        if ($ids === []) {
            return collect();
        }

        return $this->catalog->forCards($this->catalog->products()->whereIn('id', $ids))->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids, true))->take($limit)->values();
    }
}
