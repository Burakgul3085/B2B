<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Yönetici hesaplarını yalnızca süper admin yönetir. */
class EnsureStoreSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('store_admin')?->is_super, 403, 'Bu sayfa yalnızca süper admin içindir.');

        return $next($request);
    }
}
