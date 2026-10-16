<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\AccountEmailService;
use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Yeni giriş e-postasının doğrulanması. Bağlantı önce onay sayfası açar; değişiklik yalnızca butona basılınca
 * uygulanır (e-posta güvenlik tarayıcılarının bağlantıyı otomatik açıp belirteci tüketmesini önler).
 * Oturum gerekmez: bağlantı yeni adrese gelen gizli belirteçtir.
 */
class EmailVerificationController extends Controller
{
    public function __construct(private AuthTokenService $tokens) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $area = $this->area($request);
        $record = $this->tokens->find($area, 'email_change', $token);
        if (! $record) {
            return redirect()->route($area->loginRoute())->withErrors(['email' => 'Doğrulama bağlantısı geçersiz veya süresi dolmuş.']);
        }

        return view('auth.verify-email', ['area' => $area, 'token' => $token, 'newEmail' => $record->new_email]);
    }

    public function confirm(Request $request, AccountEmailService $emails): RedirectResponse
    {
        $area = $this->area($request);
        $record = $this->tokens->find($area, 'email_change', $request->scalar('token'));
        if (! $record) {
            return redirect()->route($area->loginRoute())->withErrors(['email' => 'Doğrulama bağlantısı geçersiz veya süresi dolmuş.']);
        }

        try {
            $user = $emails->confirm($area, $record);
        } catch (DomainException $e) {
            return redirect()->route($area->loginRoute())->withErrors(['email' => $e->getMessage()]);
        }

        $message = "Giriş e-postanız {$user->email} olarak güncellendi.";

        return auth($area->guard())->check()
            ? redirect()->route($area->value.'.account')->with('status', $message)
            : redirect()->route($area->loginRoute())->with('status', $message.' Yeni adresinizle giriş yapabilirsiniz.');
    }

    private function area(Request $request): Area
    {
        return Area::fromRequest($request) ?? abort(404);
    }
}
