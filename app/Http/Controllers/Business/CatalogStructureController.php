<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\VariantGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Kategori, marka ve varyant grupları — tek sayfada, sekmeli. */
class CatalogStructureController extends Controller
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['brands', 'variants'], true) ? $request->query('tab') : 'categories';

        return view('business.catalog.structure', [
            'tab' => $tab,
            'categories' => Category::whereNull('parent_id')->with('children')->orderBy('sort')->orderBy('name')->get(),
            'productCounts' => Product::notArchived()->selectRaw('category_id, count(*) c')->groupBy('category_id')->pluck('c', 'category_id'),
            'brands' => Brand::withCount(['products' => fn ($q) => $q->whereNull('archived_at')])->orderBy('name')->get(),
            'variantGroups' => VariantGroup::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);
        $category = Category::create($data);
        $this->audit->log('catalog.category_created', $category, [], $data);

        return back()->with('status', 'Kategori eklendi.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category);

        if (! empty($data['parent_id']) && $category->children()->exists()) {
            return back()->withErrors(['parent_id' => 'Alt kategorisi olan bir kategori başka kategorinin altına taşınamaz (en fazla iki seviye).']);
        }

        $old = $category->only(array_keys($data));
        $category->update($data);
        $this->audit->log('catalog.category_updated', $category, $old, $data);

        return back()->with('status', 'Kategori güncellendi.');
    }

    /** Ürünü veya alt kategorisi olan kategori silinmez; pasifleştirilir. */
    public function destroyCategory(Category $category): RedirectResponse
    {
        if ($category->children()->exists() || Product::where('category_id', $category->id)->exists()) {
            return back()->withErrors(['category' => "\"{$category->name}\" kategorisinde ürün veya alt kategori var. Silmek yerine pasif yapabilirsiniz."]);
        }

        $category->delete();
        $this->audit->log('catalog.category_deleted', $category);

        return back()->with('status', 'Kategori silindi.');
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        $data = $this->validateBrand($request);
        $brand = Brand::create($data);
        $this->audit->log('catalog.brand_created', $brand, [], $data);

        return redirect()->route('business.catalog.structure', ['tab' => 'brands'])->with('status', 'Marka eklendi.');
    }

    public function updateBrand(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validateBrand($request, $brand);
        $brand->update($data);

        return redirect()->route('business.catalog.structure', ['tab' => 'brands'])->with('status', 'Marka güncellendi.');
    }

    public function destroyBrand(Brand $brand): RedirectResponse
    {
        if (Product::where('brand_id', $brand->id)->exists()) {
            return back()->withErrors(['brand' => "\"{$brand->name}\" markasına bağlı ürün var. Silmek yerine pasif yapabilirsiniz."]);
        }
        $brand->delete();

        return redirect()->route('business.catalog.structure', ['tab' => 'brands'])->with('status', 'Marka silindi.');
    }

    public function storeVariantGroup(Request $request): RedirectResponse
    {
        $data = $this->validateVariantGroup($request);
        VariantGroup::create($data);

        return redirect()->route('business.catalog.structure', ['tab' => 'variants'])->with('status', 'Varyant grubu eklendi.');
    }

    public function updateVariantGroup(Request $request, VariantGroup $variantGroup): RedirectResponse
    {
        $variantGroup->update($this->validateVariantGroup($request));

        return redirect()->route('business.catalog.structure', ['tab' => 'variants'])->with('status', 'Varyant grubu güncellendi.');
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        $businessId = $this->tenant->businessId();
        $request->merge(['is_active' => $request->boolean('is_active', true)]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer',
                Rule::exists('categories', 'id')->where('business_id', $businessId)->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id]))],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ], ['parent_id.exists' => 'Üst kategori yalnızca ana kategorilerden seçilebilir.'], ['name' => 'kategori adı', 'parent_id' => 'üst kategori']);

        $duplicate = Category::where('name', $data['name'])
            ->where(fn ($q) => empty($data['parent_id']) ? $q->whereNull('parent_id') : $q->where('parent_id', $data['parent_id']))
            ->when($category, fn ($q) => $q->whereKeyNot($category->id))
            ->exists();

        abort_if($duplicate, back()->withErrors(['name' => 'Aynı seviyede bu adda bir kategori zaten var.'])->withInput());

        return $data + ['sort' => 0];
    }

    private function validateBrand(Request $request, ?Brand $brand = null): array
    {
        $request->merge(['is_active' => $request->boolean('is_active', true)]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->where('business_id', $this->tenant->businessId())->ignore($brand?->id)],
            'is_active' => ['boolean'],
        ], [], ['name' => 'marka adı']);
    }

    private function validateVariantGroup(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'attributes' => ['required', 'string', 'max:200'],
        ], [], ['name' => 'grup adı', 'attributes' => 'özellikler']);

        $attributes = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['attributes'])))));
        abort_if($attributes === [] || count($attributes) > 3, back()->withErrors(['attributes' => 'Bir ile üç arasında özellik girin (ör. Renk, Boyut).'])->withInput());

        return ['name' => $data['name'], 'attribute_names' => $attributes];
    }
}
