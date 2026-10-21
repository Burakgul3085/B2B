<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Storefront\StorefrontAdmin;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBanner;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ana sayfa banner'ları: ekleme, düzenleme, sıralama, silme. */
class BannerController extends Controller
{
    private const FIELDS = ['title', 'subtitle', 'button_text', 'link_url', 'starts_at', 'ends_at', 'is_active'];

    public function __construct(private StorefrontAdmin $storefront) {}

    public function index(): View
    {
        return view('store.admin.storefront.banners', ['banners' => StoreBanner::orderBy('sort')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'max:8192'],
            'mobile_image' => ['nullable', 'file', 'max:8192'],
        ], [], ['image' => 'görsel', 'mobile_image' => 'mobil görsel']);

        try {
            $this->storefront->createBanner($request->only(self::FIELDS) + ['is_active' => true], $request->file('image'), $request->file('mobile_image'));
        } catch (DomainException $e) {
            return back()->withErrors(['image' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Banner eklendi.');
    }

    public function update(Request $request, StoreBanner $storeBanner): RedirectResponse
    {
        $request->validate([
            'image' => ['nullable', 'file', 'max:8192'],
            'mobile_image' => ['nullable', 'file', 'max:8192'],
        ], [], ['image' => 'görsel', 'mobile_image' => 'mobil görsel']);

        try {
            $this->storefront->updateBanner($storeBanner, $request->only(self::FIELDS) + ['is_active' => $request->boolean('is_active')],
                $request->file('image'), $request->file('mobile_image'), $request->boolean('remove_mobile'));
        } catch (DomainException $e) {
            return back()->withErrors(['image' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Banner kaydedildi.');
    }

    public function move(Request $request, StoreBanner $storeBanner): RedirectResponse
    {
        $this->storefront->moveBanner($storeBanner, $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction']);

        return back();
    }

    public function destroy(StoreBanner $storeBanner): RedirectResponse
    {
        $this->storefront->deleteBanner($storeBanner);

        return back()->with('status', 'Banner silindi.');
    }

    /** Panelde önizleme görseli. */
    public function image(Request $request, StoreBanner $storeBanner): StreamedResponse
    {
        $path = $request->boolean('mobil') ? $storeBanner->mobile_image_path : $storeBanner->image_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400']);
    }
}
