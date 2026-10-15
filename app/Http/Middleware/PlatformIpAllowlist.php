<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** PLATFORM_ALLOWED_IPS tanımlıysa platform paneline yalnızca bu adreslerden erişilir. */
class PlatformIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_filter(array_map('trim', explode(',', (string) config('b2b.platform_allowed_ips'))));

        if ($allowed !== [] && ! in_array($request->ip(), $allowed, true)) {
            abort(404);
        }

        return $next($request);
    }
}
