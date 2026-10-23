<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Catalog\CategoryService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCategory;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Mağaza kategorileri: ağaç görünümü, ekleme, düzenleme, sıralama, silme. */
class CategoryController extends Controller
{
    private const FIELDS = ['name', 'parent_id', 'slug', 'description', 'color', 'tagline', 'seo_title', 'seo_description', 'is_active'];

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
        $request->validate(['image' => ['nullable', 'file', 'max:6144']], [], ['image' => 'görsel']);
        try {
            $this->categories->update($storeCategory, $request->only(self::FIELDS) + ['is_active' => $request->boolean('is_active')]);
            if ($request->hasFile('image') || $request->boolean('remove_image')) {
                $this->categories->setImage($storeCategory, $request->file('image'));
            }
        } catch (DomainException $e) {
            return back()->withErrors(['parent_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('store_admin.categories.index')->with('status', 'Kategori kaydedildi.');
    }

    public function image(StoreCategory $storeCategory): StreamedResponse
    {
        abort_unless($storeCategory->image_path && Storage::disk('local')->exists($storeCategory->image_path), 404);

        return Storage::disk('local')->response($storeCategory->image_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400']);
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
