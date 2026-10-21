<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Catalog\CategoryService;
use App\Domain\Store\Catalog\ProductService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Mağaza ürünleri: liste, yeni ürün (ilk varyantıyla), düzenleme, silme, toplu yayınlama. */
class ProductController extends Controller
{
    private const SORTS = ['name' => 'name', 'updated' => 'updated_at', 'price' => 'min_price'];

    public function __construct(private ProductService $products) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'brand' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['published', 'draft', 'featured', 'no_image'])],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $products = $this->filtered($filters)
            ->with(['brand', 'category.parent.parent', 'primaryImage'])
            ->withCount('variants')
            ->withMin('variants as min_price', 'price')
            ->withMax('variants as max_price', 'price')
            ->orderBy(self::SORTS[$filters['sort'] ?? 'updated'], ($filters['dir'] ?? ($filters['sort'] ?? null ? 'asc' : 'desc')))
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('store.admin.catalog.products.index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => CategoryService::options(),
            'brands' => StoreBrand::orderBy('name')->pluck('name', 'id')->all(),
            'hasAnyProduct' => StoreProduct::exists(),
        ]);
    }

    public function create(): View
    {
        return view('store.admin.catalog.products.create', $this->formData(new StoreProduct));
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $product = $this->products->create(
                $request->only(['name', 'slug', 'category_id', 'brand_id', 'variant_name', 'description', 'seo_title', 'seo_description', 'is_published', 'is_featured']),
                (array) $request->input('variant', []),
            );
        } catch (ValidationException $e) {
            // Varyant alanları formda variant[...] adındadır.
            $variantKeys = ['sku', 'barcode', 'option_value', 'price', 'compare_at_price', 'vat_rate', 'desi', 'weight_grams'];
            $errors = collect($e->errors())->mapWithKeys(fn ($messages, $key) => [(in_array($key, $variantKeys, true) ? "variant.{$key}" : $key) => $messages]);

            return back()->withErrors($errors->all())->withInput();
        }

        return redirect()->route('store_admin.products.edit', $product)
            ->with('status', 'Ürün oluşturuldu. Görsellerini ve varsa diğer seçeneklerini bu sayfadan ekleyin.');
    }

    public function edit(StoreProduct $storeProduct): View
    {
        $storeProduct->load(['variants', 'images', 'category']);

        return view('store.admin.catalog.products.edit', $this->formData($storeProduct));
    }

    public function update(Request $request, StoreProduct $storeProduct): RedirectResponse
    {
        try {
            $this->products->update($storeProduct, $request->only(['name', 'slug', 'category_id', 'brand_id', 'variant_name', 'description', 'seo_title', 'seo_description', 'is_published', 'is_featured', 'first_option_value']));
        } catch (DomainException $e) {
            return back()->withErrors(['product' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Ürün bilgileri kaydedildi.');
    }

    public function destroy(StoreProduct $storeProduct): RedirectResponse
    {
        $this->products->delete($storeProduct);

        return redirect()->route('store_admin.products.index')->with('status', "\"{$storeProduct->name}\" silindi.");
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['publish', 'unpublish'])],
        ]);

        $count = $this->products->setPublished($data['ids'], $data['action'] === 'publish');

        return back()->with('status', $data['action'] === 'publish' ? "{$count} ürün yayına alındı." : "{$count} ürün yayından kaldırıldı.");
    }

    private function filtered(array $filters): Builder
    {
        return StoreProduct::query()
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$term}%")->orWhere('barcode', $term))))
            ->when($filters['category'] ?? null, function ($q, $id) {
                $category = StoreCategory::find($id);
                $q->whereIn('category_id', $category ? $category->descendantIds() : [0]);
            })
            ->when($filters['brand'] ?? null, fn ($q, $id) => $q->where('brand_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'published' => $q->where('is_published', true),
                'draft' => $q->where('is_published', false),
                'featured' => $q->where('is_featured', true),
                'no_image' => $q->doesntHave('images'),
            });
    }

    private function formData(StoreProduct $product): array
    {
        return [
            'product' => $product,
            'categories' => CategoryService::options(),
            'brands' => StoreBrand::orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
