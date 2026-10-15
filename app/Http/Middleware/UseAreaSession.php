<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Area;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oturum başlatılmadan önce çalışır: isteğin ait olduğu alana göre çerez adını ve yolunu belirler.
 * /bayi isteğine tarayıcı toptancı veya platform çerezini hiç göndermez.
 */
class UseAreaSession
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Önceki istekten kalmış bir işletme bağlamı asla taşınmaz.
        $this->tenant->clear();

        $area = Area::fromRequest($request);

        config([
            'session.cookie' => $area?->cookieName() ?? 'b2b_public',
            'session.path' => $area ? '/'.$area->prefix() : '/',
        ]);

        // Uzun ömürlü süreçlerde (Octane, testler) oturum deposu önceki istekten kalmış olabilir;
        // çerez adı her istekte bu alana göre yeniden atanır.
        app('session')->driver()->setName(config('session.cookie'));

        // $request->user() ve auth.session middleware'i bu alanın guard'ını kullanır.
        Auth::shouldUse(($area ?? Area::Dealer)->guard());

        return $next($request);
    }
}
