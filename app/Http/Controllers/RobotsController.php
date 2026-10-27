<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Response;

/**
 * Arama motorları. Varsayılan (__invoke): tamamen kapalı (mağaza yönetimi, kapalı mağaza).
 * B2B alanında (b2b()): yalnızca bayi girişi, toptancı girişi ve bayilik başvuru formu açıktır; paneller, platform,
 * kod ekranı ve başvuru yanıt bağlantıları kapalıdır. Mağazanın kendi robots.txt'si: Store\Front\SeoController.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function b2b(): Response
    {
        // Google en uzun eşleşen kuralı uygular; "$" adresin sonunu belirtir.
        $body = "User-agent: *\n"
            ."Allow: /$\n"
            ."Allow: /bayi/login$\n"
            ."Allow: /toptanci/login$\n"
            ."Allow: /basvuru$\n"
            ."Allow: /basvuru/\n"
            ."Allow: /logo/\n"
            ."Disallow: /\n"
            ."\nSitemap: ".route('sitemap')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** B2B site haritası: bayi girişi, toptancı girişi ve etkin toptancının bayilik başvuru formu. */
    public function sitemap(): Response
    {
        $urls = collect([['loc' => route('dealer.login'), 'lastmod' => null], ['loc' => route('business.login'), 'lastmod' => null]])
            ->merge(Business::where('status', 'active')->get(['slug', 'updated_at'])
                ->map(fn ($b) => ['loc' => route('public.application', $b->slug), 'lastmod' => $b->updated_at]));

        return response()->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
