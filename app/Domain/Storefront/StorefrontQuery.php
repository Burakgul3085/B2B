<?php

namespace App\Domain\Storefront;

use App\Domain\Ordering\CartService;
use App\Domain\Pricing\PriceResolver;
use App\Models\Category;
use App\Models\DealerRelationship;
use App\Models\HomeBanner;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Showcase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Bayi ana sayfasının verileri: bayinin görebildiği slaytlar ve vitrinler.
 * Ürünler yalnızca bayinin sipariş verebileceği ürünlerdir; fiyatlar bayiye özeldir, stokta olmayanlar sona düşer.
 */
class StorefrontQuery
{
    public function __construct(private PriceResolver $prices) {}

    /** @return Collection<int, HomeBanner> her slayta `url` (tıklanınca gidilecek adres) eklenir */
    public function banners(DealerRelationship $relationship, string $placement = 'hero'): Collection
    {
        return HomeBanner::visibleTo($relationship)->where('placement', $placement)->orderBy('sort')->orderBy('id')->get()
            ->each(fn (HomeBanner $b) => $b->setAttribute('url', $this->linkUrl($b->link_type, $b->link_id)));
    }

    /**
     * @return Collection<int, array{showcase: Showcase, products: Collection, more_url: string}>
     */
    public function showcases(DealerRelationship $relationship, int $warehouseId): Collection
    {
        $qtyExpr = "(SELECT COALESCE(ws.quantity, 0) FROM warehouse_stock ws WHERE ws.product_id = products.id AND ws.warehouse_id = {$warehouseId})";

        $sections = Showcase::where('is_active', true)->orderBy('sort')->orderBy('id')->get()
            ->map(fn (Showcase $showcase) => [
                'showcase' => $showcase,
                'products' => $this->products($showcase, $qtyExpr),
                'more_url' => $this->moreUrl($showcase),
            ])
            ->filter(fn ($section) => $section['products']->isNotEmpty())
            ->values();

        // Bütün vitrinlerin fiyatları tek seferde çözülür.
        $all = $sections->flatMap(fn ($s) => $s['products'])->unique('id')->values();
        $prices = $this->prices->resolveMany($relationship, $all);

        return $sections->map(fn ($s) => $s + ['prices' => $prices]);
    }

    private function products(Showcase $showcase, string $qtyExpr): Collection
    {
        $limit = max(4, min(24, (int) $showcase->product_limit));
        $base = fn (): Builder => CartService::orderableProducts()
            ->with(['unit', 'brand', 'taxRate', 'primaryImage'])
            ->select('products.*')->selectRaw("COALESCE({$qtyExpr}, 0) as available_qty")
            ->orderByRaw("CASE WHEN COALESCE({$qtyExpr}, 0) > 0 THEN 0 ELSE 1 END");

        return match ($showcase->type) {
            'manual' => $this->manual($showcase, $base, $limit),
            'category' => $base()->whereIn('category_id', $this->categoryWithChildren($showcase->source_id))->orderBy('name')->limit($limit)->get(),
            'brand' => $base()->where('brand_id', $showcase->source_id)->orderBy('name')->limit($limit)->get(),
            'new' => $base()->latest('id')->limit($limit)->get(),
            'best_sellers' => $this->bestSellers($base, $limit),
            default => collect(),
        };
    }

    private function manual(Showcase $showcase, \Closure $base, int $limit): Collection
    {
        $order = $showcase->items()->pluck('product_id')->flip();
        if ($order->isEmpty()) {
            return collect();
        }

        // Toptancının belirlediği sıra korunur; stokta olmayanlar yine sona alınır.
        return $base()->whereIn('products.id', $order->keys())->get()
            ->sortBy(fn (Product $p) => [(float) $p->available_qty > 0 ? 0 : 1, $order[$p->id]])
            ->take($limit)->values();
    }

    /** Son 90 günde en çok onaylanan ürünler; satış yoksa en yeni ürünler. */
    private function bestSellers(\Closure $base, int $limit): Collection
    {
        $ranked = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['approved', 'preparing', 'completed'])->where('approved_at', '>=', now()->subDays(90)))
            ->selectRaw('product_id, SUM(quantity_approved) as qty')->groupBy('product_id')->orderByDesc('qty')->limit(60)
            ->pluck('qty', 'product_id');

        if ($ranked->isEmpty()) {
            return $base()->latest('id')->limit($limit)->get();
        }

        $rank = $ranked->keys()->flip();

        return $base()->whereIn('products.id', $ranked->keys())->get()
            ->sortBy(fn (Product $p) => [(float) $p->available_qty > 0 ? 0 : 1, $rank[$p->id]])
            ->take($limit)->values();
    }

    private function categoryWithChildren(?int $id): array
    {
        return $id ? [$id, ...Category::where('parent_id', $id)->pluck('id')->all()] : [];
    }

    public function linkUrl(string $type, ?int $id): ?string
    {
        return match ($type) {
            'catalog' => route('dealer.catalog'),
            'category' => $id ? route('dealer.catalog', ['category' => [$id]]) : null,
            'brand' => $id ? route('dealer.catalog', ['brand' => [$id]]) : null,
            'product' => $id && CartService::orderableProducts()->whereKey($id)->exists() ? route('dealer.catalog.show', $id) : null,
            'showcase' => ($showcase = $id ? Showcase::find($id) : null) ? $this->moreUrl($showcase) : null,
            default => null,
        };
    }

    private function moreUrl(Showcase $showcase): string
    {
        return match ($showcase->type) {
            'category' => route('dealer.catalog', ['category' => [$showcase->source_id]]),
            'brand' => route('dealer.catalog', ['brand' => [$showcase->source_id]]),
            'new' => route('dealer.catalog', ['yeni' => 1]),
            'manual' => route('dealer.catalog', ['showcase' => $showcase->id]),
            default => route('dealer.catalog'),
        };
    }
}
