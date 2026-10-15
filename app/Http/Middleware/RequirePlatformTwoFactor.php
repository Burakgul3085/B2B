<?php

namespace App\Http\Middleware;

use App\Models\PlatformAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform panelinde iki adımlı doğrulama zorunludur: kurulmamışsa kurulum ekranına,
 * kurulmuş ama bu oturumda doğrulanmamışsa kod ekranına yönlendirir.
 */
class RequirePlatformTwoFactor
{
    public const SESSION_KEY = 'platform.two_factor_passed';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var PlatformAdmin $admin */
        $admin = Auth::guard('platform')->user();

        if (! $admin->canSignIn()) {
            Auth::guard('platform')->logout();
            $request->session()->invalidate();

            return redirect()->route('platform.login');
        }

        if (! $admin->hasTwoFactorEnabled()) {
            return redirect()->route('platform.two-factor.setup');
        }

        if (! $request->session()->get(self::SESSION_KEY)) {
            return redirect()->route('platform.two-factor.challenge');
        }

        return $next($request);
    }
}
