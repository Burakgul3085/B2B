<?php

namespace App\Http\Middleware;

use App\Models\Store\StoreAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mağaza yönetimi: pasife alınan hesabın oturumu hemen kapanır. Süper admin için iki adımlı doğrulama zorunludur;
 * yönetici kendisi açtıysa her girişte kod istenir.
 */
class RequireStoreTwoFactor
{
    public const SESSION_KEY = 'store_admin.two_factor_passed';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var StoreAdmin $admin */
        $admin = Auth::guard('store_admin')->user();

        if (! $admin->canSignIn()) {
            Auth::guard('store_admin')->logout();
            $request->session()->invalidate();

            return redirect()->route('store_admin.login')->withErrors(['email' => 'Hesabınız kullanıma kapatıldı.']);
        }

        if ($admin->is_super && ! $admin->hasTwoFactorEnabled()) {
            return redirect()->route('store_admin.two-factor.setup');
        }

        if ($admin->hasTwoFactorEnabled() && ! $request->session()->get(self::SESSION_KEY)) {
            return redirect()->route('store_admin.two-factor.challenge');
        }

        return $next($request);
    }
}
