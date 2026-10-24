<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Support\Decimal;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
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
        // Puan yalnızca onaylı gerçek yorumlardan (rating_avg, rating_count).
        return $query->with(['brand:id,name,slug', 'primaryImage', 'cardImages' => fn ($q) => $q->limit(2), 'activeVariants'])
            ->withAvg('approvedReviews as rating_avg', 'rating')->withCount('approvedReviews as rating_count');
    }

    /** "Yeni gelenler" ve "Yeni" rozeti: son kaç günde yayınlanan ürünler (Mağaza Ayarları). */
    public static function newDays(): int
    {
        $days = (int) StoreSetting::get('new_product_days');

        return $days > 0 ? $days : 30;
    }

    public static function isNew(StoreProduct $product): bool
    {
        return $product->published_at !== null && $product->published_at->gt(now()->subDays(self::newDays()));
    }

    /**
     * Ortak filtreler: kategoriler (alt kategorileriyle), markalar, arama, fiyat aralığı (KDV dahil, en ucuz seçenek),
     * stokta olanlar, indirimdekiler, yeni gelenler, sıralama.
     *
     * @param  array{kategori?: list<int|string>, marka?: list<int|string>, q?: ?string, min?: ?string, max?: ?string, stokta?: bool, indirim?: bool, yeni?: bool, sirala?: ?string}  $filters
     */
    public function filter(Builder $query, array $filters): Builder
    {
        $min = Decimal::parse($filters['min'] ?? null, 2);
        $max = Decimal::parse($filters['max'] ?? null, 2);
        $cheapest = StoreProductVariant::selectRaw('MIN(price)')->whereColumn('product_id', 'store_products.id')->where('is_active', true);
        $categories = array_map('intval', (array) ($filters['kategori'] ?? []));

        $query->when($categories, fn ($q) => $q->whereIn('category_id', $this->withDescendants($categories)))
            ->when(filled($filters['q'] ?? null), fn ($q) => $this->matching($q, (string) $filters['q']))
            ->when($filters['yeni'] ?? false, fn ($q) => $q->where('published_at', '>=', now()->subDays(self::newDays())))
            ->when($filters['marka'] ?? [], fn ($q, $brands) => $q->whereIn('brand_id', array_map('intval', (array) $brands)))
            ->when($min !== null, fn ($q) => $q->where($cheapest, '>=', $min))
            ->when($max !== null, fn ($q) => $q->where($cheapest, '<=', $max))
            ->when($filters['stokta'] ?? false, fn ($q) => $q->whereHas('activeVariants', fn ($v) => $v->where('stock_quantity', '>', 0)))
            ->when($filters['indirim'] ?? false, fn ($q) => $q->whereHas('activeVariants', fn ($v) => $v->whereColumn('compare_at_price', '>', 'price')));

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

    /**
     * Filtre kutusundaki kategori ağacı: menü ağacı, her düğümde alt kategorileri dahil listedeki ürün sayısı (product_count).
     *
     * @return Collection<int, StoreCategory>
     */
    public function categoryFacets(Builder $base): Collection
    {
        $direct = (clone $base)->reorder()->groupBy('category_id')->selectRaw('category_id, COUNT(*) as aggregate')->pluck('aggregate', 'category_id');
        $count = function (StoreCategory $node) use (&$count, $direct): int {
            $total = (int) ($direct[$node->id] ?? 0) + $node->children->sum(fn ($child) => $count($child));
            $node->setAttribute('product_count', $total);

            return $total;
        };
        $roots = $this->menu();
        $roots->each(fn ($root) => $count($root));

        return $roots;
    }

    /** @return Collection<int, StoreCategory> menü ağacı: görünür kök kategoriler, görünür alt kategorileri yüklü */
    public function menu(): Collection
    {
        $all = StoreCategory::where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'parent_id', 'name', 'slug', 'color', 'tagline', 'image_path']);
        $byParent = $all->groupBy(fn ($c) => $c->parent_id ?? 0);
        foreach ($all as $category) {
            $category->setRelation('children', $byParent->get($category->id, collect())->values());
        }
        // Ana kategorinin yapboz rengi (seçilmemişse sırayla); alt kategoriler bunu taşır. Kaydedilmez, yalnızca görünüm içindir.
        $roots = $byParent->get(0, collect())->values();
        foreach ($roots as $i => $root) {
            $root->setAttribute('palette', Palette::forIndex($root->color, $i));
            foreach ($root->children as $child) {
                $child->setAttribute('palette', $root->palette);
            }
        }

        return $roots;
    }

    /** Kategori ve tüm görünür alt kategorilerindeki ürünler. */
    public function inCategory(StoreCategory $category): Builder
    {
        return $this->products()->whereIn('category_id', $category->descendantIds());
    }

    /** Ad, stok kodu, barkod veya marka adıyla arama. Her kelime eşleşmelidir. */
    public function search(string $term): Builder
    {
        return $this->matching($this->products(), $term);
    }

    /** @param  list<int>  $ids  @return list<int> kategoriler ve tüm alt kategorileri */
    private function withDescendants(array $ids): array
    {
        return StoreCategory::whereIn('id', $ids)->get()->flatMap(fn (StoreCategory $c) => $c->descendantIds())->unique()->values()->all();
    }

    private function matching(Builder $query, string $term): Builder
    {
        foreach (array_slice(preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)->orWhere('barcode', $word)->orWhere('option_value', 'like', $like)));
        }

        return $query;
    }
}
