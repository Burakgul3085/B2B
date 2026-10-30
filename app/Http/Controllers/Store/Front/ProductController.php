<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Reviews\ReviewService;
use App\Domain\Store\Storefront\Catalog;
use App\Domain\Store\Storefront\Favorites;
use App\Domain\Store\Storefront\ProductPage;
use App\Domain\Store\Storefront\RecentlyViewed;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreReview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ürün sayfası: görseller, seçenekler (fiyat ve satılabilir stok seçeneğe göre değişir), açıklama, benzer ürünler. */
class ProductController extends Controller
{
    public function __construct(private Catalog $catalog, private StoreStockService $stock, private ReviewService $reviews, private RecentlyViewed $recent) {}

    public function show(Request $request, string $slug): View
    {
        $product = $this->catalog->products()->where('slug', $slug)
            ->with(['brand', 'images', 'activeVariants', 'category'])
            ->firstOrFail();

        $available = $this->stock->available($product->activeVariants->pluck('id')->all());
        $chain = $product->category && $product->category->is_active ? $product->category->ancestry() : [];

        $related = $product->category_id
            ? $this->catalog->forCards($this->catalog->products()->where('category_id', $product->category_id)->whereKeyNot($product->id))
                ->orderByDesc('is_featured')->latest('published_at')->limit(8)->get()
            : collect();

        $customer = $request->user('customer');
        $recent = $this->recent->products($product->id);
        $this->recent->push($product->id);

        return view('store.front.product', [
            'product' => $product,
            'available' => $available,
            'breadcrumbs' => [
                ...array_map(fn ($c) => [$c->name, route('store.category', $c->slug)], $chain),
                [$product->name, null],
            ],
            'related' => $related,
            'info' => ProductPage::info(),
            'reviews' => StoreReview::approved()->where('product_id', $product->id)->with('photos')->latest('id')->limit(20)->get(),
            'reviewSummary' => ReviewService::summary($product),
            'reviewEligibility' => $this->reviews->eligibility($customer, $product),
            'isFavorite' => Favorites::has($product->id),
            'recent' => $recent,
        ]);
    }
}
