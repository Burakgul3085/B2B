<?php

namespace App\Http\Middleware;

use App\Models\Store\StoreSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mağaza kapalıyken ziyaretçi "yakında" sayfasını görür (503, arama motorları dizine eklemez).
 * Mağaza yöneticisi panelden aldığı imzalı bağlantıyla önizleme çerezi alır ve kapalı mağazayı gezebilir.
 */
class EnsureStoreOpen
{
    public const PREVIEW_COOKIE = 'magaza_onizleme';

    public function handle(Request $request, Closure $next): Response
    {
        $preview = ! StoreSetting::isOpen() && $request->cookie(self::PREVIEW_COOKIE) === '1';

        if (! StoreSetting::isOpen() && ! $preview) {
            return response()->view('store.front.coming-soon', [], 503)->header('Retry-After', '86400');
        }

        View::share('storePreview', $preview);

        return $next($request);
    }
}
