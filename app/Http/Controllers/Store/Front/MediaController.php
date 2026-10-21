<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Storefront\ImageVariants;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBanner;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vitrin görselleri (herkese açık, önbelleklenebilir). Yalnızca yayındaki ürünlerin, etkin banner ve markaların
 * görselleri sunulur. ?w= ile küçük boyut istenebilir.
 */
class MediaController extends Controller
{
    public function __construct(private ImageVariants $variants) {}

    public function product(Request $request, StoreProductImage $storeImage): StreamedResponse
    {
        abort_unless(StoreProduct::whereKey($storeImage->product_id)->where('is_published', true)->exists(), 404);

        return $this->serve($storeImage->path, $request->integer('w') ?: null);
    }

    public function banner(Request $request, StoreBanner $storeBanner): StreamedResponse
    {
        abort_unless($storeBanner->is_active, 404);
        $path = $request->boolean('mobil') && $storeBanner->mobile_image_path ? $storeBanner->mobile_image_path : $storeBanner->image_path;

        return $this->serve($path, $request->integer('w') ?: null);
    }

    public function brand(StoreBrand $storeBrand): StreamedResponse
    {
        abort_unless($storeBrand->is_active && $storeBrand->logo_path, 404);

        return $this->serve($storeBrand->logo_path, null);
    }

    private function serve(string $source, ?int $width): StreamedResponse
    {
        $path = $this->variants->path($source, $width) ?? abort(404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
