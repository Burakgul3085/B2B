<?php

namespace App\Providers;

use App\Domain\Store\Content\SitePages;
use App\Domain\Store\Content\StoreFooter;
use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\StoreSetting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/** Mağaza vitrini: ortak düzenin (menü, ayarlar, alt bilgi sayfaları) verisi. */
class StoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('components.layouts.store', fn ($view) => $view->with([
            'storeMenu' => app(Catalog::class)->menu(),
            'storeSettings' => StoreSetting::values(),
            'footerPages' => SitePages::published(),
            'storeFooter' => StoreFooter::viewData(),
            'storeCustomer' => auth('customer')->user(),
            'cartCount' => app(CurrentCart::class)->count(request()),
        ]));
    }
}
