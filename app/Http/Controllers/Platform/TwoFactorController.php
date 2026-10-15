<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RequirePlatformTwoFactor;
use App\Models\PlatformAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Platform yöneticisi için zorunlu iki adımlı doğrulama (kurulum ve her girişte kod). */
class TwoFactorController extends Controller
{
    private const SETUP_SESSION_KEY = 'platform.two_factor_pending_secret';

    public function __construct(private TwoFactor $twoFactor, private AuditLogger $audit) {}

    public function setup(Request $request): View|RedirectResponse
    {
        $admin = $this->admin($request);
        if ($admin->hasTwoFactorEnabled()) {
            return redirect()->route('platform.two-factor.challenge');
        }

        $secret = $request->session()->get(self::SETUP_SESSION_KEY);
        if (! $secret) {
            $secret = $this->twoFactor->generateSecret();
            $request->session()->put(self::SETUP_SESSION_KEY, $secret);
        }

        return view('platform.two-factor.setup', [
            'qr' => $this->twoFactor->qrSvg(config('app.name'), $admin->email, $secret),
            'secret' => $secret,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:10']])['code'];
        $secret = (string) $request->session()->get(self::SETUP_SESSION_KEY);

        if ($secret === '' || ! $this->twoFactor->verify($secret, $code, "2fa:platform:{$admin->id}")) {
            return back()->withErrors(['code' => 'Kod doğrulanamadı. Uygulamadaki güncel 6 haneli kodu girin.']);
        }

        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget(self::SETUP_SESSION_KEY);
        $request->session()->put(RequirePlatformTwoFactor::SESSION_KEY, true);
        $request->session()->regenerate();

        $this->audit->log('auth.two_factor_enabled', $admin, actorArea: Area::Platform, actorId: $admin->id);

        return redirect()->route('platform.dashboard');
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        $admin = $this->admin($request);
        if (! $admin->hasTwoFactorEnabled()) {
            return redirect()->route('platform.two-factor.setup');
        }

        return view('platform.two-factor.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:10']])['code'];
        $key = "2fa-attempts:platform:{$admin->id}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Çok fazla hatalı kod. Lütfen birkaç dakika sonra tekrar deneyin.']);
        }

        if (! $this->twoFactor->verify((string) $admin->two_factor_secret, $code, "2fa:platform:{$admin->id}")) {
            RateLimiter::hit($key, 300);
            $this->audit->log('auth.two_factor_failed', $admin, actorArea: Area::Platform, actorId: $admin->id);

            return back()->withErrors(['code' => 'Kod hatalı veya süresi geçmiş.']);
        }

        RateLimiter::clear($key);
        $request->session()->put(RequirePlatformTwoFactor::SESSION_KEY, true);
        $request->session()->regenerate();

        return redirect()->route('platform.dashboard');
    }

    private function admin(Request $request): PlatformAdmin
    {
        return $request->user('platform');
    }
}
