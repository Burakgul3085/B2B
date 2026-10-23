<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Content\SitePages;
use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RobotsController;
use App\Models\Store\StoreBlogPost;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreSetting;
use Illuminate\Http\Response;

/**
 * Arama motorları: robots.txt ve sitemap.xml. Mağaza kapalıyken tamamı kapalıdır.
 * B2B ve mağaza yönetimi her zaman kapalıdır (RobotsController).
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        if (! StoreSetting::isOpen()) {
            return app(RobotsController::class)();
        }

        $body = "User-agent: *\nAllow: /\nDisallow: /ara\nDisallow: /onizleme\n\nSitemap: ".route('store.sitemap')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Catalog $catalog): Response
    {
        abort_unless(StoreSetting::isOpen(), 404);

        $urls = collect([['loc' => route('store.home'), 'lastmod' => null], ['loc' => route('store.products'), 'lastmod' => null], ['loc' => route('store.brands'), 'lastmod' => null]]);
        $urls = $urls
            ->merge(StoreCategory::where('is_active', true)->get(['slug', 'updated_at'])->map(fn ($c) => ['loc' => route('store.category', $c->slug), 'lastmod' => $c->updated_at]))
            ->merge(StoreBrand::where('is_active', true)->whereHas('products', fn ($q) => $q->where('is_published', true))->get(['slug', 'updated_at'])
                ->map(fn ($b) => ['loc' => route('store.brand', $b->slug), 'lastmod' => $b->updated_at]))
            ->merge($catalog->products()->get(['slug', 'updated_at'])->map(fn ($p) => ['loc' => route('store.product', $p->slug), 'lastmod' => $p->updated_at]))
            ->merge(collect(SitePages::published())->map(fn ($p) => ['loc' => $p['url'], 'lastmod' => $p['updated_at']]))
            ->merge(StoreBlogPost::live()->get(['slug', 'updated_at'])->map(fn ($p) => ['loc' => route('store.blog.post', $p->slug), 'lastmod' => $p->updated_at]));

        return response()->view('store.front.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
