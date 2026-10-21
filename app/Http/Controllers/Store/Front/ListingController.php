<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ürün listeleri: tüm ürünler, kategori, marka ve arama. Hepsi aynı filtre ve sıralamayı kullanır. */
class ListingController extends Controller
{
    public function __construct(private Catalog $catalog) {}

    public function index(Request $request): View
    {
        return $this->render($request, $this->catalog->products(), [
            'title' => 'Tüm Ürünler',
            'breadcrumbs' => [['Tüm Ürünler', null]],
        ]);
    }

    public function category(Request $request, string $slug): View
    {
        $category = StoreCategory::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $chain = $category->ancestry();
        abort_if(collect($chain)->contains(fn ($c) => ! $c->is_active), 404);

        return $this->render($request, $this->catalog->inCategory($category), [
            'title' => $category->name,
            'description' => $category->description,
            'seoTitle' => $category->seo_title,
            'seoDescription' => $category->seo_description,
            'canonical' => route('store.category', $category->slug),
            'category' => $category,
            'subcategories' => $category->children()->where('is_active', true)->get(['id', 'name', 'slug']),
            'breadcrumbs' => array_map(fn ($c) => [$c->name, $c->id === $category->id ? null : route('store.category', $c->slug)], $chain),
        ]);
    }

    public function brand(Request $request, string $slug): View
    {
        $brand = StoreBrand::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->render($request, $this->catalog->products()->where('brand_id', $brand->id), [
            'title' => $brand->name,
            'canonical' => route('store.brand', $brand->slug),
            'brand' => $brand,
            'hideBrandFilter' => true,
            'breadcrumbs' => [['Markalar', route('store.brands')], [$brand->name, null]],
        ]);
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->validate(['q' => ['nullable', 'string', 'max:100']])['q']);

        return $this->render($request, $term === '' ? $this->catalog->products()->whereRaw('1 = 0') : $this->catalog->search($term), [
            'title' => $term === '' ? 'Arama' : "“{$term}” için sonuçlar",
            'searchTerm' => $term,
            'noindex' => true,
            'breadcrumbs' => [['Arama', null]],
        ]);
    }

    private function render(Request $request, Builder $base, array $data): View
    {
        $filters = $request->validate([
            'marka' => ['nullable', 'array', 'max:50'],
            'marka.*' => ['integer'],
            'min' => ['nullable', 'string', 'max:20'],
            'max' => ['nullable', 'string', 'max:20'],
            'stokta' => ['nullable', 'boolean'],
            'sirala' => ['nullable', Rule::in(array_keys(Catalog::SORTS))],
        ]);
        $filtered = array_filter($filters, fn ($v) => $v !== null && $v !== [] && $v !== '');

        $products = $this->catalog->forCards($this->catalog->filter(clone $base, $filters))
            ->paginate(Catalog::PER_PAGE)
            ->withQueryString();

        return view('store.front.listing', $data + [
            'products' => $products,
            'filters' => $filters,
            'brandFacets' => ($data['hideBrandFilter'] ?? false) ? collect() : $this->catalog->brandFacets($base),
            // Filtrelenmiş veya sayfalanmış listeler dizine eklenmez; kanonik adres filtresiz liste olur.
            'noindex' => ($data['noindex'] ?? false) || array_diff_key($filtered, ['sirala' => 1]) !== [] || $products->currentPage() > 1,
        ]);
    }
}
