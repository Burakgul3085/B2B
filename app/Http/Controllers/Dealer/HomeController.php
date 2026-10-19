<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Ordering\CartService;
use App\Domain\Storefront\StorefrontQuery;
use App\Http\Controllers\Concerns\ServesBannerMedia;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\HomeBanner;
use App\Models\Order;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Bayi ana sayfası (vitrin): slaytlar, kısa hesap özeti ve ürün vitrinleri. */
class HomeController extends Controller
{
    use ServesBannerMedia, UsesDealerContext;

    public function __invoke(StorefrontQuery $storefront, CartService $cart): View
    {
        $relationship = $this->relationship()->load('currentAccount');
        $settings = $this->settings();

        $showcases = $storefront->showcases($relationship, (int) $settings->default_warehouse_id);
        $productIds = $showcases->flatMap(fn ($s) => $s['products']->pluck('id'));

        return view('dealer.home', [
            'banners' => $storefront->banners($relationship),
            'strips' => $storefront->banners($relationship, 'strip'),
            'showcases' => $showcases,
            'settings' => $settings,
            'openRequests' => ProductRequest::where('dealer_relationship_id', $relationship->id)->whereIn('status', ProductRequest::OPEN)
                ->whereIn('product_id', $productIds)->pluck('product_id')->flip(),
            'summary' => [
                'balance' => (string) ($relationship->currentAccount?->balance ?? '0'),
                'open_orders' => Order::where('dealer_relationship_id', $relationship->id)->whereIn('status', [...Order::PENDING, ...Order::ACTIVE_FULFILMENT])->count(),
                'cart' => $cart->count($relationship),
            ],
        ]);
    }

    /** Slayt medyası: yalnızca bayinin şu an görebildiği slaytlar. */
    public function media(Request $request, HomeBanner $banner): BinaryFileResponse
    {
        abort_unless(HomeBanner::visibleTo($this->relationship())->whereKey($banner->id)->exists(), 404);

        return $this->bannerMedia($request, $banner);
    }
}
