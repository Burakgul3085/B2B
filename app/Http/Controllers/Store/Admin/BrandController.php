<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Catalog\BrandService;
use App\Domain\Support\BrandLibrary;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBrand;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Mağaza markaları ve logoları. */
class BrandController extends Controller
{
    public function __construct(private BrandService $brands) {}

    public function index(BrandLibrary $library): View
    {
        $brands = StoreBrand::withCount('products')->orderBy('name')->get();

        return view('store.admin.catalog.brands', [
            'brands' => $brands,
            'library' => $library->suggestions(fn (string $slug) => route('store_admin.brands.library-logo', $slug)),
            'existing' => $brands->pluck('name')->all(),
        ]);
    }

    /** Kütüphaneden seçildiyse ad ve logo kütüphaneden gelir (tarayıcıdan gelen ad değil); değilse yazılan adla eklenir. */
    public function store(Request $request, BrandLibrary $library): RedirectResponse
    {
        $picked = $library->find($request->input('library'));
        $brand = $this->brands->create(['name' => $picked['name'] ?? $request->input('name'), 'is_active' => true]);

        $logo = $picked ? $library->logoUpload($picked['slug']) : null;
        if ($logo) {
            try {
                $this->brands->setLogo($brand, $logo);
            } catch (DomainException) {
                return back()->with('status', "\"{$brand->name}\" markası eklendi; logo eklenemedi, elle yükleyebilirsiniz.");
            }
        }

        return back()->with('status', "\"{$brand->name}\" markası ".($logo ? 'logosuyla ' : '').'eklendi.');
    }

    /** Kütüphane logosu önizlemesi (yalnızca kütüphanedeki dosyalar). */
    public function libraryLogo(BrandLibrary $library, string $slug): BinaryFileResponse
    {
        $path = $library->logoPath($slug);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function update(Request $request, StoreBrand $storeBrand): RedirectResponse
    {
        $this->brands->update($storeBrand, $request->only('name') + ['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Marka kaydedildi.');
    }

    public function destroy(StoreBrand $storeBrand): RedirectResponse
    {
        try {
            $this->brands->delete($storeBrand);
        } catch (DomainException $e) {
            return back()->withErrors(['brand' => $e->getMessage()]);
        }

        return back()->with('status', "\"{$storeBrand->name}\" silindi.");
    }

    public function logo(Request $request, StoreBrand $storeBrand): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'file', 'max:2048']], [], ['logo' => 'logo']);

        try {
            $this->brands->setLogo($storeBrand, $request->file('logo'));
        } catch (DomainException $e) {
            return back()->withErrors(['brand' => $e->getMessage()]);
        }

        return back()->with('status', 'Logo güncellendi.');
    }

    public function removeLogo(StoreBrand $storeBrand): RedirectResponse
    {
        $this->brands->removeLogo($storeBrand);

        return back()->with('status', 'Logo kaldırıldı.');
    }
}
