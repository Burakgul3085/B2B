<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Content\HelpPages;
use App\Domain\Store\Content\SitePages;
use App\Domain\Store\Print\PrintSettings;
use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RobotsController;
use App\Models\Store\StoreBlogCategory;
use App\Models\Store\StoreBlogPost;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreCollection;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StorePage;
use App\Models\Store\StoreSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Arama motorları: robots.txt ve sitemap.xml. Mağaza kapalıyken tamamı kapalıdır.
 * Mağaza yönetimi her zaman kapalıdır; B2B'de yalnızca bayi / toptancı girişi ve başvuru formu açıktır (RobotsController).
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        if (! StoreSetting::isOpen()) {
            return app(RobotsController::class)();
        }

        // Kişiye özel ve işlem sayfaları taranmaz (sepet, ödeme, hesap, sipariş bağlantıları, arama, yardımcı uç noktalar).
        // "/odeme-secenekleri" ve "/siparis-ve-teslimat" açık kalsın diye önekler sonu belirtilerek yazılır.
        $private = ['/ara', '/onizleme', '/sepet', '/odeme$', '/odeme/', '/siparis/', '/hesabim', '/favoriler/', '/giris', '/uye-ol',
            '/parolami-unuttum', '/parola-sifirla', '/bulten/', '/stok-bildirimi', '/menu/', '/ilceler/', '/cikis', '/fotokopi/'];
        $body = "User-agent: *\nAllow: /\n".implode('', array_map(fn ($p) => "Disallow: {$p}\n", $private))
            ."\nSitemap: ".route('store.sitemap')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Herkese açık tüm sayfalar: katalog, markalar, seçkiler, kampanyalar, kurumsal ve yardım sayfaları, yasal metinler, blog. */
    public function sitemap(Catalog $catalog): Response
    {
        abort_unless(StoreSetting::isOpen(), 404);

        $page = fn (string $route, $lastmod = null) => ['loc' => route($route), 'lastmod' => $lastmod];
        $about = StorePage::where('key', 'about')->where('is_published', true)->first();

        $urls = collect([$page('store.home'), $page('store.products'), $page('store.brands'), $page('store.campaigns')])
            ->merge(StoreCategory::where('is_active', true)->get(['slug', 'updated_at'])->map(fn ($c) => ['loc' => route('store.category', $c->slug), 'lastmod' => $c->updated_at]))
            ->merge(StoreBrand::where('is_active', true)->whereHas('products', fn ($q) => $q->where('is_published', true))->get(['slug', 'updated_at'])
                ->map(fn ($b) => ['loc' => route('store.brand', $b->slug), 'lastmod' => $b->updated_at]))
            ->merge(StoreCollection::where('is_active', true)->get(['slug', 'updated_at'])->map(fn ($c) => ['loc' => route('store.collection', $c->slug), 'lastmod' => $c->updated_at]))
            ->merge($catalog->products()->get(['slug', 'updated_at'])->map(fn ($p) => ['loc' => route('store.product', $p->slug), 'lastmod' => $p->updated_at]))
            ->merge(collect(SitePages::published())->map(fn ($p) => ['loc' => $p['url'], 'lastmod' => $p['updated_at']]))
            ->when(app(PrintSettings::class)->enabled(), fn ($u) => $u->push($page('store.print')))
            ->when($about && ! empty($about->content['gallery']) && SitePages::sectionOn($about->content, 'gallery'), fn ($u) => $u->push($page('store.about.gallery', $about->updated_at)))
            ->merge(collect(array_keys(HelpPages::PAGES))->filter(fn ($key) => HelpPages::isPublished($key))->map(fn ($key) => $page('store.help.'.$key)))
            ->merge(collect(array_keys(StoreLegalDocument::KEYS))->map(fn ($key) => ['loc' => route('store.legal', $key), 'lastmod' => StoreLegalDocument::current($key)?->created_at]))
            ->when(StoreBlogPost::live()->exists(), fn ($u) => $u->push($page('store.blog', StoreBlogPost::live()->max('updated_at') ? Carbon::parse(StoreBlogPost::live()->max('updated_at')) : null)))
            ->merge(StoreBlogCategory::whereHas('posts', fn ($q) => $q->live())->get(['slug', 'updated_at'])->map(fn ($c) => ['loc' => route('store.blog.category', $c->slug), 'lastmod' => $c->updated_at]))
            ->merge(StoreBlogPost::live()->get(['slug', 'updated_at'])->map(fn ($p) => ['loc' => route('store.blog.post', $p->slug), 'lastmod' => $p->updated_at]));

        return response()->view('sitemap', ['urls' => $urls->unique('loc')->values()], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
