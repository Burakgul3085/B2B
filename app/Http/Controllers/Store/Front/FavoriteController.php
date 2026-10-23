<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Storefront\Catalog;
use App\Domain\Store\Storefront\Favorites;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Favoriler (yalnızca üyeler): kalp düğmesi ve Hesabım → Favorilerim. */
class FavoriteController extends Controller
{
    public function __construct(private Favorites $favorites, private Catalog $catalog) {}

    public function index(Request $request): View
    {
        return view('store.front.account.favorites', ['products' => $this->favorites->products($request->user('customer'))]);
    }

    public function toggle(Request $request, int $product): JsonResponse|RedirectResponse
    {
        $model = $this->catalog->products()->whereKey($product)->firstOrFail();
        $on = $this->favorites->toggle($request->user('customer'), $model);
        $message = $on ? 'Favorilerinize eklendi.' : 'Favorilerinizden çıkarıldı.';

        return $request->expectsJson()
            ? response()->json(['favorite' => $on, 'message' => $message])
            : back()->with('status', $message);
    }
}
