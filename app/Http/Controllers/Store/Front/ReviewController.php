<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Reviews\ReviewService;
use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreReview;
use App\Models\Store\StoreReviewPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ürün yorumları (müşteri): ürün sayfasından veya Hesabım → Değerlendirmelerim'den fotoğraflı yorum gönderme;
 * yorum yönetici onayından sonra yayımlanır.
 */
class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    /** Hesabım → Değerlendirmelerim: teslim alınıp henüz yorumlanmayan ürünler ve üyenin yorumları. */
    public function index(Request $request): View
    {
        $customer = $request->user('customer');

        return view('store.front.account.reviews', [
            'awaiting' => $this->reviews->awaiting($customer),
            'reviews' => StoreReview::where('customer_id', $customer->id)->with(['product:id,name,slug', 'product.primaryImage', 'photos'])->latest('id')->get(),
        ]);
    }

    public function store(Request $request, string $slug, Catalog $catalog): RedirectResponse
    {
        $product = $catalog->products()->where('slug', $slug)->firstOrFail();
        $this->reviews->submit($request->user('customer'), $product, $request->only(['rating', 'title', 'body']) + ['photos' => $request->file('photos', [])]);

        return $request->input('from') === 'account'
            ? redirect()->route('store.reviews')->with('status', "Teşekkürler! \"{$product->name}\" yorumunuz incelendikten sonra yayımlanacak.")
            : redirect()->to(route('store.product', $product->slug).'#yorumlar')->with('review_sent', true);
    }

    public function photo(Request $request, int $photo): Response
    {
        $photo = StoreReviewPhoto::with('review')->findOrFail($photo);
        abort_unless(ReviewService::canSeePhoto($photo, $request->user('customer')) && Storage::disk('local')->exists($photo->path), 404);
        $public = $photo->review->status === 'approved';

        return Storage::disk('local')->response($photo->path, null, [
            'Cache-Control' => $public ? 'public, max-age=86400' : 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
