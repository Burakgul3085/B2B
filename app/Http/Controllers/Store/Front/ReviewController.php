<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Reviews\ReviewService;
use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Ürün sayfasından yorum gönderme; yorum yönetici onayından sonra yayımlanır. */
class ReviewController extends Controller
{
    public function store(Request $request, string $slug, Catalog $catalog, ReviewService $reviews): RedirectResponse
    {
        $product = $catalog->products()->where('slug', $slug)->firstOrFail();
        $reviews->submit($request->user('customer'), $product, $request->only(['rating', 'title', 'body']));

        return redirect()->to(route('store.product', $product->slug).'#yorumlar')->with('review_sent', true);
    }
}
