<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Support\Decimal;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Vitrinde ziyaretçiye görünen katalog. Yalnızca yayındaki ve en az bir etkin varyantı olan ürünler listelenir;
 * menüde yalnızca görünür kategoriler, markalar sayfasında ürünü olan görünür markalar yer alır.
 */
class Catalog
{
    public const SORTS = [
        'onerilen' => 'Önerilen',
        'yeni' => 'En yeniler',
        'fiyat-artan' => 'Fiyat (artan)',
        'fiyat-azalan' => 'Fiyat (azalan)',
        'ad' => 'Ada göre (A–Z)',
    ];

    public const PER_PAGE = 24;

    public function products(): Builder
    {
        return StoreProduct::query()
            ->where('is_published', true)
            ->whereHas('activeVariants');
    }

    /** Kart için gerekli ilişkiler. */
    public function forCards(Builder $query): Builder
    {
        return $query->with(['brand:id,name,slug', 'primaryImage', 'activeVariants']);
    }

    /**
     * Ortak filtreler: markalar, fiyat aralığı (KDV dahil, en ucuz seçenek), stokta olanlar, sıralama.
     *
     * @param  array{marka?: list<int|string>, min?: ?string, max?: ?string, stokta?: bool, sirala?: ?string}  $filters
     */
    public function filter(Builder $query, array $filters): Builder
    {
        $min = Decimal::parse($filters['min'] ?? null, 2);
        $max = Decimal::parse($filters['max'] ?? null, 2);
        $cheapest = StoreProductVariant::selectRaw('MIN(price)')->whereColumn('product_id', 'store_products.id')->where('is_active', true);

        $query->when($filters['marka'] ?? [], fn ($q, $brands) => $q->whereIn('brand_id', array_map('intval', (array) $brands)))
            ->when($min !== null, fn ($q) => $q->where($cheapest, '>=', $min))
            ->when($max !== null, fn ($q) => $q->where($cheapest, '<=', $max))
            ->when($filters['stokta'] ?? false, fn ($q) => $q->whereHas('activeVariants', fn ($v) => $v->where('stock_quantity', '>', 0)));

        return match ($filters['sirala'] ?? 'onerilen') {
            'yeni' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'fiyat-artan' => $query->orderBy($cheapest)->orderBy('id'),
            'fiyat-azalan' => $query->orderByDesc($cheapest)->orderBy('id'),
            'ad' => $query->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /**
     * Listedeki ürünlerin markaları ve ürün sayıları (filtre kutusu için).
     *
     * @return Collection<int, StoreBrand>
     */
    public function brandFacets(Builder $base): Collection
    {
        $counts = (clone $base)->reorder()->whereNotNull('brand_id')->groupBy('brand_id')->selectRaw('brand_id, COUNT(*) as aggregate')->pluck('aggregate', 'brand_id');

        return StoreBrand::whereIn('id', $counts->keys())->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug'])
            ->each(fn ($b) => $b->setAttribute('products_count', (int) $counts[$b->id]));
    }

    /** @return Collection<int, StoreCategory> menü ağacı: görünür kök kategoriler, görünür alt kategorileri yüklü */
    public function menu(): Collection
    {
        $all = StoreCategory::where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'parent_id', 'name', 'slug']);
        $byParent = $all->groupBy(fn ($c) => $c->parent_id ?? 0);
        foreach ($all as $category) {
            $category->setRelation('children', $byParent->get($category->id, collect())->values());
        }

        return $byParent->get(0, collect())->values();
    }

    /** Kategori ve tüm görünür alt kategorilerindeki ürünler. */
    public function inCategory(StoreCategory $category): Builder
    {
        return $this->products()->whereIn('category_id', $category->descendantIds());
    }

    /** Ad, stok kodu, barkod veya marka adıyla arama. Her kelime eşleşmelidir. */
    public function search(string $term): Builder
    {
        $query = $this->products();
        foreach (array_slice(preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)->orWhere('barcode', $word)->orWhere('option_value', 'like', $like)));
        }

        return $query;
    }
}
