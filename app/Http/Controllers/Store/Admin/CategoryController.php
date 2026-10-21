<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Catalog\CategoryService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCategory;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Mağaza kategorileri: ağaç görünümü, ekleme, düzenleme, sıralama, silme. */
class CategoryController extends Controller
{
    private const FIELDS = ['name', 'parent_id', 'slug', 'description', 'seo_title', 'seo_description', 'is_active'];

    public function __construct(private CategoryService $categories) {}

    public function index(Request $request): View
    {
        $editing = $request->integer('duzenle') ? StoreCategory::find($request->integer('duzenle')) : null;

        return view('store.admin.catalog.categories', [
            'tree' => CategoryService::tree(),
            'parents' => CategoryService::options(onlyParents: true),
            'editing' => $editing,
            'editParents' => $editing ? CategoryService::options($editing, onlyParents: true) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $category = $this->categories->create($request->only(self::FIELDS) + ['is_active' => true]);
        } catch (DomainException $e) {
            return back()->withErrors(['parent_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "\"{$category->name}\" kategorisi eklendi.");
    }

    public function update(Request $request, StoreCategory $storeCategory): RedirectResponse
    {
        try {
            $this->categories->update($storeCategory, $request->only(self::FIELDS) + ['is_active' => $request->boolean('is_active')]);
        } catch (DomainException $e) {
            return back()->withErrors(['parent_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('store_admin.categories.index')->with('status', 'Kategori kaydedildi.');
    }

    public function move(Request $request, StoreCategory $storeCategory): RedirectResponse
    {
        $this->categories->move($storeCategory, $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction']);

        return back();
    }

    public function destroy(StoreCategory $storeCategory): RedirectResponse
    {
        try {
            $this->categories->delete($storeCategory);
        } catch (DomainException $e) {
            return back()->withErrors(['category' => $e->getMessage()]);
        }

        return redirect()->route('store_admin.categories.index')->with('status', "\"{$storeCategory->name}\" silindi.");
    }
}
