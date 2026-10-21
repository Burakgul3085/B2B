<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireStoreTwoFactor;
use App\Models\Store\StoreAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Mağaza yönetimi iki adımlı doğrulaması (TOTP). Süper admin için zorunludur; yönetici isteğe bağlı açar ve kapatır.
 */
class TwoFactorController extends Controller
{
    private const SETUP_SESSION_KEY = 'store_admin.two_factor_pending_secret';

    public function __construct(private TwoFactor $twoFactor, private AuditLogger $audit) {}

    public function setup(Request $request): View|RedirectResponse
    {
        $admin = $this->admin($request);
        if ($admin->hasTwoFactorEnabled()) {
            return redirect()->route('store_admin.two-factor.challenge');
        }

        $secret = $request->session()->get(self::SETUP_SESSION_KEY);
        if (! $secret) {
            $secret = $this->twoFactor->generateSecret();
            $request->session()->put(self::SETUP_SESSION_KEY, $secret);
        }

        return view('store.admin.two-factor.setup', [
            'qr' => $this->twoFactor->qrSvg(config('store.name').' Yönetim', $admin->email, $secret),
            'secret' => $secret,
            'required' => $admin->is_super,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:10']])['code'];
        $secret = (string) $request->session()->get(self::SETUP_SESSION_KEY);

        if ($secret === '' || ! $this->twoFactor->verify($secret, $code, "2fa:store_admin:{$admin->id}")) {
            return back()->withErrors(['code' => 'Kod doğrulanamadı. Uygulamadaki güncel 6 haneli kodu girin.']);
        }

        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget(self::SETUP_SESSION_KEY);
        $request->session()->put(RequireStoreTwoFactor::SESSION_KEY, true);
        $request->session()->regenerate();

        $this->audit->log('store.two_factor_enabled', $admin);

        return redirect()->route('store_admin.dashboard')->with('status', 'İki adımlı doğrulama etkinleştirildi.');
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        $admin = $this->admin($request);
        if (! $admin->hasTwoFactorEnabled()) {
            return redirect()->route($admin->is_super ? 'store_admin.two-factor.setup' : 'store_admin.dashboard');
        }

        return view('store.admin.two-factor.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:10']])['code'];
        $key = "2fa-attempts:store_admin:{$admin->id}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Çok fazla hatalı kod. Lütfen birkaç dakika sonra tekrar deneyin.']);
        }

        if (! $this->twoFactor->verify((string) $admin->two_factor_secret, $code, "2fa:store_admin:{$admin->id}")) {
            RateLimiter::hit($key, 300);
            $this->audit->log('store.two_factor_failed', $admin);

            return back()->withErrors(['code' => 'Kod hatalı veya süresi geçmiş.']);
        }

        RateLimiter::clear($key);
        $request->session()->put(RequireStoreTwoFactor::SESSION_KEY, true);
        $request->session()->regenerate();

        return redirect()->route('store_admin.dashboard');
    }

    /** Yönetici kendi iki adımlı doğrulamasını kapatabilir; süper admin için zorunlu olduğundan kapatılamaz. */
    public function disable(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);
        abort_if($admin->is_super, 403, 'Süper admin için iki adımlı doğrulama zorunludur.');
        $request->validate(['two_factor_password' => ['required', 'string', 'current_password:store_admin']], [], ['two_factor_password' => 'mevcut parola']);

        $admin->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $request->session()->forget(RequireStoreTwoFactor::SESSION_KEY);
        $this->audit->log('store.two_factor_disabled', $admin);

        return back()->with('status', 'İki adımlı doğrulama kapatıldı.');
    }

    private function admin(Request $request): StoreAdmin
    {
        return $request->user('store_admin');
    }
}
