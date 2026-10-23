<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Storefront\CollectionAdmin;
use App\Domain\Store\Storefront\HomeLayout;
use App\Domain\Store\Storefront\Palette;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCollection;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Vitrin → Ana Sayfa (blok sırası) ve Vitrin → Seçkiler. */
class CollectionController extends Controller
{
    private const FIELDS = ['title', 'subtitle', 'color', 'product_skus', 'is_active'];

    public function __construct(private CollectionAdmin $collections) {}

    public function home(): View
    {
        return view('store.admin.storefront.home', ['blocks' => HomeLayout::blocks()]);
    }

    public function updateHome(Request $request, HomeLayout $layout): RedirectResponse
    {
        $layout->save((array) $request->input('blocks', []));

        return back()->with('status', 'Ana sayfa düzeni kaydedildi.');
    }

    public function index(): View
    {
        return view('store.admin.storefront.collections.index', [
            'collections' => StoreCollection::withCount('products')->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('store.admin.storefront.collections.form', ['collection' => new StoreCollection, 'skus' => '', 'colors' => Palette::COLORS]);
    }

    public function edit(StoreCollection $storeCollection): View
    {
        return view('store.admin.storefront.collections.form', ['collection' => $storeCollection, 'skus' => CollectionAdmin::skus($storeCollection), 'colors' => Palette::COLORS]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, StoreCollection $storeCollection): RedirectResponse
    {
        return $this->save($request, $storeCollection);
    }

    public function destroy(StoreCollection $storeCollection): RedirectResponse
    {
        $this->collections->delete($storeCollection);

        return redirect()->route('store_admin.collections.index')->with('status', "“{$storeCollection->title}” silindi.");
    }

    public function image(StoreCollection $storeCollection): StreamedResponse
    {
        abort_unless($storeCollection->image_path && Storage::disk('local')->exists($storeCollection->image_path), 404);

        return Storage::disk('local')->response($storeCollection->image_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400']);
    }

    private function save(Request $request, ?StoreCollection $collection): RedirectResponse
    {
        $request->validate(['image' => ['nullable', 'file', 'max:6144']], [], ['image' => 'görsel']);
        try {
            $collection = $this->collections->save($collection, $request->only(self::FIELDS) + ['is_active' => $request->boolean('is_active')],
                $request->file('image'), $request->boolean('remove_image'));
        } catch (DomainException $e) {
            return back()->withErrors(['image' => $e->getMessage()])->withInput();
        }

        return redirect()->route('store_admin.collections.edit', $collection)->with('status', 'Seçki kaydedildi.');
    }
}
