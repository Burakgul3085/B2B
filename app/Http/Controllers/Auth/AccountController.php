<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\AccountEmailService;
use App\Domain\Auth\Area;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Profil ve güvenlik: ad, giriş e-postası ve parola değişikliği (üç alan için ortak). */
class AccountController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function show(Request $request): View
    {
        $area = $this->area($request);

        $user = $request->user($area->guard());

        return view('account.show', ['area' => $area, 'user' => $user]);
    }

    /** Giriş e-postası değişikliği: mevcut şifreyle onaylanır ve hemen uygulanır (doğrulama bağlantısı yok). */
    public function updateEmail(Request $request, AccountEmailService $accounts): RedirectResponse
    {
        $area = $this->area($request);
        $data = $request->validate([
            'email' => ['bail', 'required', 'string', 'email', 'max:255'],
            'email_password' => ['bail', 'required', 'string', 'current_password:'.$area->guard()],
        ], [], ['email' => 'yeni e-posta adresi', 'email_password' => 'mevcut şifre']);

        try {
            $email = $accounts->changeOwnEmail($area, $request->user($area->guard()), $data['email']);
        } catch (DomainException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput($request->only('email'));
        }

        return back()->with('status', "Giriş e-postanız {$email} olarak güncellendi. Bundan sonra bu adresle giriş yapın; e-postalar da bu adrese gelir.");
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
            'current_password' => ['bail', 'required', 'string', 'current_password:'.$area->guard()],
            'password' => ['bail', 'required', 'string', 'confirmed', Password::defaults(), 'different:current_password'],
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
