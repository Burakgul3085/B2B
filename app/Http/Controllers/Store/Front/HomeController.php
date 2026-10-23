<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Storefront\HomeFeed;
use App\Domain\Store\Storefront\HomeLayout;
use App\Domain\Store\Storefront\RecentlyViewed;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBlogPost;
use App\Models\Store\StoreSetting;
use Illuminate\View\View;

/** Mağaza ana sayfası: bloklar Vitrin → Ana Sayfa'daki sırayla; veriler HomeFeed'den (yalnızca açık blokların verisi alınır). */
class HomeController extends Controller
{
    public function __invoke(HomeFeed $feed, RecentlyViewed $recent): View
    {
        $blocks = HomeLayout::blocks();
        $on = array_column(array_filter($blocks, fn ($b) => $b['enabled']), 'key');
        $want = fn (string $key) => in_array($key, $on, true);

        return view('store.front.home', [
            'blocks' => $blocks,
            'settings' => StoreSetting::values(),
            'banners' => $want('hero') ? $feed->banners() : ['main' => collect(), 'side' => collect()],
            'perks' => $want('perks') ? $feed->perks() : [],
            'categories' => $want('categories') ? $feed->categories() : collect(),
            'coupons' => $want('coupons') ? $feed->coupons() : collect(),
            'showcase' => $want('showcase') ? $feed->showcase() : [],
            'recent' => $want('recent') ? $recent->products(limit: 4) : collect(),
            'collections' => $want('collections') ? $feed->collections() : collect(),
            'brands' => $want('brands') ? $feed->brands() : collect(),
            'about' => $want('about') ? $feed->about() : null,
            'posts' => $want('blog') ? StoreBlogPost::live()->with('category:id,name,slug')->latest('published_at')->limit(3)->get() : collect(),
        ]);
    }
}
