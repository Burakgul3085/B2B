<?php

namespace App\Http\Controllers\Business;

use App\Domain\Catalog\ProductService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:'.ProductService::MAX_IMAGES],
            'images.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ], [], ['images' => 'görseller', 'images.*' => 'görsel']);

        try {
            foreach ($request->file('images') as $file) {
                $this->products->addImage($product, $file);
            }
        } catch (DomainException $e) {
            return back()->withErrors(['images' => $e->getMessage()]);
        }

        return back()->with('status', 'Görseller yüklendi.');
    }

    public function primary(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $this->products->makePrimary($image);

        return back()->with('status', 'Ana görsel güncellendi.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $this->products->removeImage($image);

        return back()->with('status', 'Görsel silindi.');
    }

    /** Görsel yalnızca işletme kapsamında bulunursa sunulur (başka işletmenin görseli 404). */
    public function show(ProductImage $image): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        return Storage::disk('local')->response($image->path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
