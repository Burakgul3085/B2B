<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Files\ImageResponse;
use App\Domain\Files\ImageVariants;
use App\Domain\Ordering\CartService;
use App\Domain\Pricing\PriceResolver;
use App\Domain\Pricing\PromotionService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Category;
use App\Models\DealerRelationship;
use App\Models\ProductRequest;
use App\Models\Promotion;
use App\Models\PromotionImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bayinin Kampanyalar sayfası (kampanya vitrini ve indirimdeki ürünler) ve kampanya detayı. Yalnızca bu bayiye
 * görünen kampanyalar açılır (PromotionService::visibleFor); bitmiş, pasif veya başka bayiye ait olan 404 döner.
 */
class PromotionController extends Controller
{
    use UsesDealerContext;

    /** Sayfada gösterilen ürün sayısı; fazlası katalogda filtreyle açılır. */
    private const PRODUCTS = 12;

    public function __construct(private PromotionService $promotions, private PriceResolver $prices) {}

    public function index(): View
    {
        $relationship = $this->relationship();
        $rows = $this->promotions->visibleFor($relationship);
        [$products, $total] = $this->products(fn ($q) => $this->prices->whereDiscounted($q, $relationship));

        return view('dealer.promotions.index', [
            'rows' => $rows,
            'categories' => $this->categoryNames($rows->pluck('promotion')),
            'discountTotal' => $total,
        ] + $this->productData($relationship, $products, $rows));
    }

    public function show(Promotion $promotion): View
    {
        $relationship = $this->relationship();
        $rows = $this->promotions->visibleFor($relationship);
        $row = $rows->first(fn (array $row) => $row['promotion']->id === $promotion->id) ?? abort(404);
        $promotion = $row['promotion']->load('images');
        [$products, $total] = $promotion->isScoped()
            ? $this->products(fn ($q) => $this->promotions->whereInScope($q, $promotion))
            : [collect(), 0];

        return view('dealer.promotions.show', [
            'promotion' => $promotion,
            'remaining' => $row['remaining'],
            'categories' => $this->categoryNames(collect([$promotion])),
            'productTotal' => $total,
        ] + $this->productData($relationship, $products, $rows));
    }

    /** Kampanya görseli: yalnızca kampanya bu bayiye görünüyorsa. */
    public function image(Request $request, PromotionImage $promotionImage, ImageVariants $variants): Response
    {
        $visible = $this->promotions->visibleFor($this->relationship())->contains(fn (array $row) => $row['promotion']->id === $promotionImage->promotion_id);
        abort_unless($visible, 404);
        $path = $variants->path($promotionImage->path, $request->integer('w') ?: null) ?? abort(404);

        return ImageResponse::make($request, $path, 'private, max-age=3600');
    }

    /** @return array{0: Collection, 1: int} sipariş verilebilir ilk ürünler (stoktakiler önce) ve toplam sayı */
    private function products(callable $scope): array
    {
        $warehouseId = (int) $this->settings()->default_warehouse_id;
        $qtyExpr = "(SELECT COALESCE(ws.quantity, 0) FROM warehouse_stock ws WHERE ws.product_id = products.id AND ws.warehouse_id = {$warehouseId})";
        $query = fn (): Builder => $scope(CartService::orderableProducts());

        $products = $query()->with(['unit', 'brand', 'taxRate', 'primaryImage'])
            ->select('products.*')->selectRaw("COALESCE({$qtyExpr}, 0) as available_qty")
            ->orderByRaw("CASE WHEN COALESCE({$qtyExpr}, 0) > 0 THEN 0 ELSE 1 END")->orderBy('name')
            ->limit(self::PRODUCTS)->get();

        return [$products, $products->count() < self::PRODUCTS ? $products->count() : $query()->count()];
    }

    /** Ürün kartının (dealer.catalog._card) beklediği veriler. */
    private function productData(DealerRelationship $relationship, Collection $products, Collection $rows): array
    {
        return [
            'products' => $products,
            'prices' => $this->prices->resolveMany($relationship, $products),
            'campaignTags' => $this->promotions->tagsFor($relationship, $products, $rows),
            'settings' => $this->settings(),
            'openRequests' => ProductRequest::where('dealer_relationship_id', $relationship->id)->whereIn('status', ProductRequest::OPEN)
                ->whereIn('product_id', $products->pluck('id'))->pluck('product_id')->flip(),
        ];
    }

    private function categoryNames(Collection $promotions): Collection
    {
        $ids = $promotions->flatMap(fn (Promotion $p) => $p->category_ids ?? [])->unique()->values()->all();

        return $ids ? Category::whereIn('id', $ids)->pluck('name', 'id') : collect();
    }
}
