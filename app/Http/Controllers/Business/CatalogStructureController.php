<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\BrandLogo;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Kategoriler ve markalar — ayrı sayfalar (mağaza yönetimindeki gibi). Varyant grupları kaldırıldı; seçenekler ürün sayfasından eklenir. */
class CatalogStructureController extends Controller
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenant) {}

    /** Kategori ağacı (her seviye); düzenleme ?duzenle=id, alt kategori ekleme ?ust=id ile soldaki formda açılır. */
    public function categories(Request $request): View
    {
        $editing = $request->integer('duzenle') ? Category::find($request->integer('duzenle')) : null;

        return view('business.catalog.categories', [
            'tree' => Category::tree(),
            'parents' => Category::paths(),
            'editing' => $editing,
            'editParents' => $editing ? Category::paths($editing) : [],
            'productCounts' => Product::notArchived()->selectRaw('category_id, count(*) c')->groupBy('category_id')->pluck('c', 'category_id'),
        ]);
    }

    public function brands(): View
    {
        return view('business.catalog.brands', [
            'brands' => Brand::withCount(['products' => fn ($q) => $q->whereNull('archived_at')])->orderBy('name')->get(),
        ]);
    }

    /** Eski "Kategori ve Markalar" adresi: sekmesine göre yeni sayfaya. */
    public function legacy(Request $request): RedirectResponse
    {
        return redirect()->route($request->query('tab') === 'brands' ? 'business.brands.index' : 'business.categories.index', status: 301);
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

        $old = $category->only(array_keys($data));
        $category->update($data);
        $this->audit->log('catalog.category_updated', $category, $old, $data);

        return redirect()->route('business.categories.index')->with('status', 'Kategori güncellendi.');
    }

    /** Ürünü veya alt kategorisi olan kategori silinmez; pasifleştirilir. */
    public function destroyCategory(Category $category): RedirectResponse
    {
        if ($category->children()->exists() || Product::where('category_id', $category->id)->exists()) {
            return back()->withErrors(['category' => "\"{$category->name}\" kategorisinde ürün veya alt kategori var. Silmek yerine pasif yapabilirsiniz."]);
        }

        $category->delete();
        $this->audit->log('catalog.category_deleted', $category);

        return redirect()->route('business.categories.index')->with('status', 'Kategori silindi.');
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        $data = $this->validateBrand($request);
        $brand = Brand::create($data);
        $this->audit->log('catalog.brand_created', $brand, [], $data);

        return redirect()->route('business.brands.index')->with('status', 'Marka eklendi.');
    }

    public function updateBrand(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validateBrand($request, $brand);
        $brand->update($data);

        return redirect()->route('business.brands.index')->with('status', 'Marka güncellendi.');
    }

    /** Marka logosu: bayi Markalar sayfasında görünür. */
    public function updateBrandLogo(Request $request, Brand $brand, BrandLogo $logos): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp']], [], ['logo' => 'logo']);

        try {
            $logos->replace($brand, $request->file('logo'));
        } catch (DomainException $e) {
            return back()->withErrors(['logo' => $e->getMessage()]);
        }

        return redirect()->route('business.brands.index')->with('status', "\"{$brand->name}\" logosu güncellendi.");
    }

    public function destroyBrandLogo(Brand $brand, BrandLogo $logos): RedirectResponse
    {
        $logos->remove($brand);

        return redirect()->route('business.brands.index')->with('status', "\"{$brand->name}\" logosu kaldırıldı.");
    }

    public function brandLogo(Brand $brand): StreamedResponse
    {
        abort_unless($brand->logo_path && Storage::disk('local')->exists($brand->logo_path), 404);

        return Storage::disk('local')->response($brand->logo_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroyBrand(Brand $brand, BrandLogo $logos): RedirectResponse
    {
        if (Product::where('brand_id', $brand->id)->exists()) {
            return back()->withErrors(['brand' => "\"{$brand->name}\" markasına bağlı ürün var. Silmek yerine pasif yapabilirsiniz."]);
        }
        if ($brand->logo_path) {
            $logos->remove($brand);
        }
        $brand->delete();

        return redirect()->route('business.brands.index')->with('status', 'Marka silindi.');
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        $businessId = $this->tenant->businessId();
        $request->merge(['is_active' => $request->boolean('is_active', true)]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'kategori adı', 'parent_id' => 'üst kategori']);

        // Kendisinin veya alt kategorisinin altına taşınamaz (döngü); ağaç güvenlik sınırını aşamaz.
        $parent = empty($data['parent_id']) ? null : Category::findOrFail($data['parent_id']);
        if ($parent && $category && in_array($parent->id, Category::withDescendants($category->id), true)) {
            abort(back()->withErrors(['parent_id' => 'Bir kategori kendisinin veya alt kategorisinin altına taşınamaz.'])->withInput());
        }
        if (($parent?->depth() ?? 0) + ($category?->height() ?? 1) > Category::MAX_DEPTH) {
            abort(back()->withErrors(['parent_id' => 'Kategori ağacı en fazla '.Category::MAX_DEPTH.' seviye olabilir.'])->withInput());
        }

        $duplicate = Category::where('name', $data['name'])
            ->where(fn ($q) => empty($data['parent_id']) ? $q->whereNull('parent_id') : $q->where('parent_id', $data['parent_id']))
            ->when($category, fn ($q) => $q->whereKeyNot($category->id))
            ->exists();

        // Hata yalnızca gerçekten varsa oturuma yazılır (abort_if'in argümanı her durumda çalışırdı).
        if ($duplicate) {
            abort(back()->withErrors(['name' => 'Aynı seviyede bu adda bir kategori zaten var.'])->withInput());
        }

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
}
