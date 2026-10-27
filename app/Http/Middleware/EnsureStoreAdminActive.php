<?php

namespace App\Http\Middleware;

use App\Models\Store\StoreAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mağaza yönetimi: pasife alınan hesabın oturumu hemen kapanır. Giriş doğrulaması e-postaya giden kodla,
 * oturum açılmadan önce yapılır (App\Domain\Auth\LoginCode).
 */
class EnsureStoreAdminActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var StoreAdmin $admin */
        $admin = Auth::guard('store_admin')->user();

        if (! $admin->canSignIn()) {
            Auth::guard('store_admin')->logout();
            $request->session()->invalidate();

            return redirect()->route('store_admin.login')->withErrors(['email' => 'Hesabınız kullanıma kapatıldı.']);
        }

        return $next($request);
    }
}
