<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBanner;
use App\Models\Store\StoreBrand;
use Illuminate\View\View;

/** Mağaza ana sayfası: banner'lar, kategoriler, öne çıkanlar, yeni ürünler, markalar. */
class HomeController extends Controller
{
    public function __invoke(Catalog $catalog): View
    {
        return view('store.front.home', [
            'banners' => StoreBanner::visible()->get(),
            'categories' => $catalog->menu(),
            'featured' => $catalog->forCards($catalog->products()->where('is_featured', true))->latest('published_at')->limit(8)->get(),
            'newest' => $catalog->forCards($catalog->products())->latest('published_at')->orderByDesc('id')->limit(8)->get(),
            'brands' => StoreBrand::where('is_active', true)->whereNotNull('logo_path')
                ->whereHas('products', fn ($q) => $q->where('is_published', true))->orderBy('name')->limit(16)->get(['id', 'name', 'slug', 'logo_path', 'updated_at']),
        ]);
    }
}
