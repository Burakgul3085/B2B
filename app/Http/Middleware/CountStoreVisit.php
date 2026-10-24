<?php

namespace App\Http\Middleware;

use App\Domain\Store\Visitors\VisitorCounter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Vitrin ziyaret sayacı. Yalnızca tarayıcıda açılan başarılı sayfalar sayılır; arka plan istekleri (sepet çekmecesi,
 * arama önerisi), ön yükleme ve yönetici önizlemesi sayılmaz. Kayıt yanıt gönderildikten sonra yapılır; bir hata
 * sayfayı etkilemez, yalnızca günlüğe yazılır.
 */
class CountStoreVisit
{
    public function __construct(private VisitorCounter $counter) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $dest = $request->header('Sec-Fetch-Dest');

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || $request->ajax() || $request->prefetch()
            || ($dest !== null && $dest !== 'document')
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            || $request->cookie(EnsureStoreOpen::PREVIEW_COOKIE) === '1') {
            return;
        }

        try {
            $this->counter->record((string) $request->ip(), (string) $request->userAgent());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
