<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Reviews\ReviewService;
use App\Domain\Store\Storefront\Catalog;
use App\Domain\Store\Storefront\Favorites;
use App\Domain\Store\Storefront\ProductPage;
use App\Domain\Store\Storefront\RecentlyViewed;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductQuestion;
use App\Models\Store\StoreReview;
use App\Models\Store\StoreReviewPhoto;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ürün sayfası: görseller, seçenekler (fiyat ve satılabilir stok seçeneğe göre değişir), açıklama, benzer ürünler;
 * değerlendirmeler (sıralama, yıldız filtresi, müşteri fotoğrafları) ve yayındaki Soru & Cevap.
 */
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
            ...$this->feedback($request, $product),
            'reviewSummary' => ReviewService::summary($product),
            'reviewEligibility' => $this->reviews->eligibility($customer, $product),
            'isFavorite' => Favorites::has($product->id),
            'recent' => $recent,
        ]);
    }

    public const REVIEW_SORTS = ['yeni' => 'En yeni', 'yuksek' => 'En yüksek puan', 'dusuk' => 'En düşük puan', 'fotografli' => 'Fotoğraflı'];

    private const PAGE = 6;

    /** Değerlendirme listesi (sıralama, yıldız, "daha fazla"), müşteri fotoğrafları şeridi ve yayındaki sorular. */
    private function feedback(Request $request, StoreProduct $product): array
    {
        $sort = array_key_exists((string) $request->query('yorum_sirala'), self::REVIEW_SORTS) ? (string) $request->query('yorum_sirala') : 'yeni';
        $star = in_array((int) $request->query('yildiz'), [1, 2, 3, 4, 5], true) ? (int) $request->query('yildiz') : null;
        $limit = min(120, max(self::PAGE, (int) $request->query('yorum_sayisi', self::PAGE)));

        $query = StoreReview::approved()->where('product_id', $product->id)
            ->when($star, fn ($q, $s) => $q->where('rating', $s))
            ->when($sort === 'fotografli', fn ($q) => $q->whereHas('photos'));
        $total = (clone $query)->count();
        $query = match ($sort) {
            'yuksek' => $query->orderByDesc('rating')->latest('id'),
            'dusuk' => $query->orderBy('rating')->latest('id'),
            default => $query->latest('id'),
        };

        $qLimit = min(100, max(5, (int) $request->query('soru_sayisi', 5)));
        $questions = StoreProductQuestion::published()->where('product_id', $product->id);

        return [
            'reviews' => $query->with('photos')->limit($limit)->get(),
            'reviewFilters' => ['sort' => $sort, 'star' => $star, 'limit' => $limit, 'total' => $total, 'page' => self::PAGE],
            'reviewPhotos' => StoreReviewPhoto::whereHas('review', fn ($r) => $r->approved()->where('product_id', $product->id))
                ->with('review:id,author_name,rating')->latest('id')->limit(16)->get(),
            'questions' => (clone $questions)->latest('answered_at')->limit($qLimit)->get(),
            'questionCount' => $questions->count(),
            'questionLimit' => $qLimit,
        ];
    }
}
