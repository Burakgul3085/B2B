<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mağaza formlarında bot koruması (docs/22-magaza-m9-raporu.md §3). Formdaki <x-store.bot-trap /> iki alan ekler:
 *  - "website": insanlara görünmeyen tuzak alanı; doluysa istek botundur.
 *  - "_ft": formun gösterildiği anın şifreli zamanı; form çok kısa sürede (varsayılan 2 sn) veya 12 saatten eski gönderilirse reddedilir.
 * Hız sınırları (throttle) ayrıca rotalarda uygulanır. Testlerde store.bot_protection kapatılabilir.
 */
class StoreBotGuard
{
    public const MIN_SECONDS = 2;

    public const MAX_SECONDS = 12 * 3600;

    /** @param  string|int  $minSeconds  en kısa doldurma süresi; girişte 0 (parola yöneticileri formu anında gönderebilir) */
    public function handle(Request $request, Closure $next, string|int $minSeconds = self::MIN_SECONDS): Response
    {
        if (! config('store.bot_protection') || ! $request->isMethod('POST')) {
            return $next($request);
        }

        $age = null;
        try {
            $age = time() - (int) Crypt::decryptString((string) $request->input('_ft'));
        } catch (DecryptException) {
        }

        if (filled($request->input('website')) || $age === null || $age < (int) $minSeconds || $age > self::MAX_SECONDS) {
            Log::info('[store.bot] form reddedildi', ['route' => $request->route()?->getName(), 'ip' => $request->ip(), 'age' => $age]);

            return back()->withInput($request->except(['password', 'password_confirmation', 'current_password', 'website', '_ft']))
                ->withErrors(['form' => 'Form doğrulanamadı. Sayfayı yenileyip tekrar deneyin.']);
        }

        return $next($request);
    }
}
