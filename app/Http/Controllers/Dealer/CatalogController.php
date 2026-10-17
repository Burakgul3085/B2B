<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Inventory\StockService;
use App\Domain\Ordering\CartService;
use App\Domain\Pricing\PriceResolver;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bayi kataloğu: yalnızca aktif, bayiye görünür ve arşivlenmemiş ürünler; bayinin kendi fiyatıyla.
 * Alış fiyatı ve iç maliyet bilgisi hiçbir yanıtta yer almaz.
 */
class CatalogController extends Controller
{
    use UsesDealerContext;

    public function __construct(private PriceResolver $prices) {}

    public function index(Request $request): View
    {
        // Eski tekli bağlantılar (?category=5) da çalışsın: tek değer diziye çevrilir.
        $request->merge(collect(['category', 'brand'])->mapWithKeys(fn ($k) => [$k => array_values(array_filter((array) $request->input($k, [])))])->all());

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['array', 'max:50'],
            'category.*' => ['integer'],
            'brand' => ['array', 'max:50'],
            'brand.*' => ['integer'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['name', 'new', 'stock'])],
            'view' => ['nullable', Rule::in(['grid', 'list'])],
        ]);
        $relationship = $this->relationship();
        $settings = $this->settings();
        $warehouseId = (int) $settings->default_warehouse_id;
        $qtyExpr = "(SELECT COALESCE(ws.quantity, 0) FROM warehouse_stock ws WHERE ws.product_id = products.id AND ws.warehouse_id = {$warehouseId})";

        $selectedCategories = array_map('intval', $filters['category']);
        $selectedBrands = array_map('intval', $filters['brand']);
        // Ana kategori seçilirse alt kategorileri de kapsanır.
        $categoryIds = $selectedCategories
            ? array_merge($selectedCategories, Category::whereIn('parent_id', $selectedCategories)->pluck('id')->all())
            : [];

        // Arama ve stok filtresi tüm sonuçlara ve filtre sayılarına uygulanır.
        $base = fn () => CartService::orderableProducts()
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = addcslashes($term, '%_\\');
                $q->where(fn ($q) => $q->where('name', 'like', "%{$like}%")->orWhere('sku', 'like', "{$like}%")->orWhere('barcode', $term));
            })
            ->when($filters['in_stock'] ?? false, fn ($q) => $q->whereRaw("COALESCE({$qtyExpr}, 0) > 0"));

        $products = $base()
            ->with(['unit', 'brand', 'taxRate', 'primaryImage'])
            ->select('products.*')->selectRaw("COALESCE({$qtyExpr}, 0) as available_qty")
            ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->when($selectedBrands, fn ($q) => $q->whereIn('brand_id', $selectedBrands))
            // Stokta olmayan ürünler her sıralamada en sona düşer (sayfalar arasında da).
            ->orderByRaw("CASE WHEN COALESCE({$qtyExpr}, 0) > 0 THEN 0 ELSE 1 END")
            ->when(($filters['sort'] ?? 'name') === 'new', fn ($q) => $q->latest('id'))
            ->when(($filters['sort'] ?? 'name') === 'stock', fn ($q) => $q->orderByRaw("COALESCE({$qtyExpr}, 0) DESC"))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        // Filtre sayıları: her grup, diğer grubun seçimine göre sayılır.
        $categoryCounts = $base()->when($selectedBrands, fn ($q) => $q->whereIn('brand_id', $selectedBrands))
            ->whereNotNull('category_id')->groupBy('category_id')->selectRaw('category_id, COUNT(*) as c')->pluck('c', 'category_id');
        $brandCounts = $base()->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->whereNotNull('brand_id')->groupBy('brand_id')->selectRaw('brand_id, COUNT(*) as c')->pluck('c', 'brand_id');

        $categories = Category::where('is_active', true)->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('name')])
            ->orderBy('sort')->orderBy('name')->get()
            ->each(function ($cat) use ($categoryCounts) {
                $cat->children->each(fn ($child) => $child->setAttribute('product_count', (int) ($categoryCounts[$child->id] ?? 0)));
                $cat->setAttribute('product_count', (int) ($categoryCounts[$cat->id] ?? 0) + $cat->children->sum('product_count'));
            });

        $brands = Brand::where('is_active', true)->whereIn('id', $brandCounts->keys()->merge($selectedBrands))->orderBy('name')->get(['id', 'name'])
            ->each(fn ($b) => $b->setAttribute('product_count', (int) ($brandCounts[$b->id] ?? 0)));

        $openRequests = ProductRequest::where('dealer_relationship_id', $relationship->id)->whereIn('status', ProductRequest::OPEN)
            ->whereIn('product_id', $products->pluck('id'))->pluck('product_id')->flip();

        return view('dealer.catalog.index', [
            'products' => $products,
            'prices' => $this->prices->resolveMany($relationship, $products->getCollection()),
            'filters' => $filters,
            'selectedCategories' => $selectedCategories,
            'selectedBrands' => $selectedBrands,
            'view' => $filters['view'] ?? 'grid',
            'settings' => $settings,
            'openRequests' => $openRequests,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    public function show(Product $product): View
    {
        $product = CartService::orderableProducts()->with(['unit', 'brand', 'taxRate', 'images', 'category.parent', 'variantGroup'])->findOrFail($product->id);
        $relationship = $this->relationship();
        $settings = $this->settings();

        // Geçmiş alımlar: bu bayinin bu üründen verdiği son siparişler.
        $history = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('dealer_relationship_id', $relationship->id))
            ->with('order:id,order_no,status,created_at')
            ->latest('id')->limit(5)->get();

        return view('dealer.catalog.show', [
            'product' => $product,
            'price' => $this->prices->resolve($relationship, $product),
            'available' => app(StockService::class)->available($product->id, (int) $settings->default_warehouse_id),
            'settings' => $settings,
            'history' => $history,
            'siblings' => $product->variant_group_id
                ? CartService::orderableProducts()->where('variant_group_id', $product->variant_group_id)->whereKeyNot($product->id)->get(['id', 'name', 'variant_values'])
                : collect(),
            'openRequest' => ProductRequest::where('dealer_relationship_id', $relationship->id)->where('product_id', $product->id)->whereIn('status', ProductRequest::OPEN)->first(),
        ]);
    }

    /** Görsel yalnızca bayinin görebildiği bir ürüne aitse sunulur. */
    public function image(ProductImage $image): StreamedResponse
    {
        abort_unless(CartService::orderableProducts()->whereKey($image->product_id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        return Storage::disk('local')->response($image->path, null, [
            'Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
