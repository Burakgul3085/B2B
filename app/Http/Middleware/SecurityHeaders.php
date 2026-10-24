<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // İçerik güvenlik politikası: yalnızca kendi kaynaklarımız; satır içi <script> ve eval yoktur (Alpine'in
        // CSP sürümü kullanılır). Bileşenlerdeki style özellikleri için 'unsafe-inline' (stil) gerekir.
        // Vite geliştirme sunucusu (npm run dev) çalışırken politika uygulanmaz.
        if (! Vite::isRunningHot()) {
            // Google Analytics yalnızca mağaza vitrininde ve ölçüm kimliği tanımlıysa izinlidir.
            $analytics = config('store.analytics_id') && $request->getHost() === config('store.domain');
            $gaHosts = 'https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com';

            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'".($analytics ? ' https://www.googletagmanager.com' : ''),
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob:".($analytics ? ' https://*.google-analytics.com https://*.googletagmanager.com' : ''),
                "media-src 'self' blob:", // vitrin slayt formunda seçilen videonun önizlemesi (blob:)
                "font-src 'self'",
                'frame-src https://www.google.com', // iletişim sayfasındaki Google Haritalar
                "connect-src 'self'".($analytics ? " {$gaHosts}" : ''),
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
