<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Files\ImageResponse;
use App\Domain\Store\Content\SitePages;
use App\Domain\Store\Storefront\ImageVariants;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBanner;
use App\Models\Store\StoreBlogPost;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreCollection;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreNewsletterCampaign;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductImage;
use App\Models\Store\StoreSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vitrin görselleri (herkese açık, önbelleklenebilir). Yalnızca yayındaki ürünlerin, etkin banner ve markaların
 * görselleri sunulur. ?w= ile küçük boyut istenebilir. Rotalar oturumsuz çalışır (routes/store.php): görsel isteği
 * oturum okuyup yazmaz. Ürün görselinin dosyası kayıt boyunca değişmez; bu yüzden bir yıl, değişmez olarak
 * önbelleğe alınır. Diğer görseller (banner, kategori…) aynı adreste yenilenebildiği için bir hafta tutulur ve
 * süre dolunca ETag ile denetlenir (değişmediyse 304).
 */
class MediaController extends Controller
{
    public function __construct(private ImageVariants $variants) {}

    public function product(Request $request, StoreProductImage $storeImage): Response
    {
        abort_unless(StoreProduct::whereKey($storeImage->product_id)->where('is_published', true)->exists(), 404);

        return $this->serve($request, $storeImage->path, $request->integer('w') ?: null, 'public, max-age=31536000, immutable');
    }

    public function banner(Request $request, StoreBanner $storeBanner): Response
    {
        abort_unless($storeBanner->is_active, 404);
        $path = $request->boolean('mobil') && $storeBanner->mobile_image_path ? $storeBanner->mobile_image_path : $storeBanner->image_path;

        return $this->serve($request, $path, $request->integer('w') ?: null);
    }

    public function brand(Request $request, StoreBrand $storeBrand): Response
    {
        abort_unless($storeBrand->is_active && $storeBrand->logo_path, 404);

        return $this->serve($request, $storeBrand->logo_path, null);
    }

    /** Alt bilgideki ödeme logo bandı. */
    public function paymentLogos(Request $request): Response
    {
        return $this->serve($request, StoreSetting::get('payment_logos_path') ?? abort(404), null);
    }

    /** Kategori görseli (geniş menü, ana sayfa kutuları). */
    public function category(Request $request, StoreCategory $storeCategory): Response
    {
        abort_unless($storeCategory->is_active && $storeCategory->image_path, 404);

        return $this->serve($request, $storeCategory->image_path, $request->integer('w') ?: null);
    }

    /** Kampanya kartı görseli (Kampanyalar sayfası). */
    public function discount(Request $request, StoreDiscount $storeDiscount): Response
    {
        abort_unless($storeDiscount->show_on_page && $storeDiscount->isLive() && $storeDiscount->image_path, 404);

        return $this->serve($request, $storeDiscount->image_path, $request->integer('w') ?: null);
    }

    /** Seçki görseli (ana sayfa kutusu, seçki sayfası). */
    public function collection(Request $request, StoreCollection $storeCollection): Response
    {
        abort_unless($storeCollection->is_active && $storeCollection->image_path, 404);

        return $this->serve($request, $storeCollection->image_path, $request->integer('w') ?: null);
    }

    /** E-bülten görseli (e-postalardan yüklenir; gizli içerik değildir). */
    public function newsletter(Request $request, StoreNewsletterCampaign $storeNewsletterCampaign): Response
    {
        abort_unless($storeNewsletterCampaign->image_path, 404);

        return $this->serve($request, $storeNewsletterCampaign->image_path, $request->integer('w') ?: null);
    }

    public function blog(Request $request, StoreBlogPost $storeBlogPost): Response
    {
        abort_unless($storeBlogPost->isLive() && $storeBlogPost->cover_path, 404);

        return $this->serve($request, $storeBlogPost->cover_path, $request->integer('w') ?: null);
    }

    /** Hakkımızda sayfasının kapak ve galeri fotoğrafları (yalnızca yayındaki sayfanın). */
    public function page(Request $request, string $file): Response
    {
        abort_unless(SitePages::ownsImage($file), 404);

        return $this->serve($request, 'store/pages/'.$file, $request->integer('w') ?: null);
    }

    private function serve(Request $request, string $source, ?int $width, string $cache = 'public, max-age=604800'): Response
    {
        return ImageResponse::make($request, $this->variants->path($source, $width) ?? abort(404), $cache);
    }
}
