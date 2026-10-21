<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Catalog\ProductService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductImage;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ürün görselleri ve marka logoları: yükleme, kaldırma, ana görsel yapma ve yönetim panelinde gösterme. */
class ProductImageController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function store(Request $request, StoreProduct $storeProduct): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:'.ProductService::MAX_IMAGES],
            'images.*' => ['file', 'max:5120'],
        ], [], ['images' => 'görseller', 'images.*' => 'görsel']);

        $added = 0;
        foreach ($request->file('images') as $file) {
            try {
                $this->products->addImage($storeProduct, $file);
                $added++;
            } catch (DomainException $e) {
                return back()->withErrors(['images' => $e->getMessage()])->with('status', $added ? "{$added} görsel eklendi." : null);
            }
        }

        return back()->with('status', $added === 1 ? 'Görsel eklendi.' : "{$added} görsel eklendi.");
    }

    public function primary(StoreProductImage $storeImage): RedirectResponse
    {
        $this->products->makePrimary($storeImage);

        return back()->with('status', 'Ana görsel değiştirildi.');
    }

    public function destroy(StoreProductImage $storeImage): RedirectResponse
    {
        $this->products->removeImage($storeImage);

        return back()->with('status', 'Görsel kaldırıldı.');
    }

    public function show(StoreProductImage $storeImage): StreamedResponse
    {
        return $this->serve($storeImage->path);
    }

    public function brandLogo(StoreBrand $storeBrand): StreamedResponse
    {
        abort_unless($storeBrand->logo_path, 404);

        return $this->serve($storeBrand->logo_path);
    }

    private function serve(string $path): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
