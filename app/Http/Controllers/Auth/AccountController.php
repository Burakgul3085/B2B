<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Profil ve güvenlik: ad ve parola değişikliği (üç alan için ortak). */
class AccountController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function show(Request $request): View
    {
        $area = $this->area($request);

        return view('account.show', ['area' => $area, 'user' => $request->user($area->guard())]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $area = $this->area($request);
        $user = $request->user($area->guard());
        $data = $request->validate(['name' => ['required', 'string', 'max:120']], [], ['name' => 'ad soyad']);

        $user->update($data);

        return back()->with('status', 'Profil bilgileriniz güncellendi.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $area = $this->area($request);
        $user = $request->user($area->guard());

        $request->validate([
            'current_password' => ['required', 'current_password:'.$area->guard()],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ], [], ['current_password' => 'mevcut parola', 'password' => 'yeni parola']);

        $user->forceFill(['password' => $request->input('password'), 'remember_token' => Str::random(60)])->save();

        // Diğer cihazlardaki oturumlar sonlanır; bu oturum yenilenir.
        Auth::guard($area->guard())->logoutOtherDevices($request->input('password'));
        $request->session()->regenerate();

        $this->audit->log('auth.password_changed', $user);

        return back()->with('status', 'Parolanız değiştirildi.');
    }

    private function area(Request $request): Area
    {
        return Area::fromRequest($request) ?? abort(404);
    }
}
