<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\AccountEmailService;
use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Domain\Store\Customers\CustomerService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\StoreCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Müşteri girişi, üyelik, çıkış ve "Parolamı unuttum". Girişte misafir sepeti hesaba katılır.
 * Hesap varlığı sızdırılmaz: hatalı girişte ve sıfırlama isteğinde yanıt aynıdır.
 */
class AuthController extends Controller
{
    public function __construct(private AuditLogger $audit, private CurrentCart $cart) {}

    public function showLogin(): View
    {
        return view('store.front.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [], ['email' => 'e-posta', 'password' => 'parola']);
        $email = Str::lower(trim($data['email']));
        $limits = config('b2b.login_limits');
        $keys = ['customer-login:'.sha1($email.'|'.$request->ip()) => $limits['per_email'], 'customer-login-ip:'.$request->ip() => $limits['per_ip']];

        foreach ($keys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages(['email' => 'Çok fazla başarısız deneme. Lütfen '.ceil(RateLimiter::availableIn($key) / 60).' dakika sonra tekrar deneyin.']);
            }
        }

        $customer = StoreCustomer::where('email', $email)->first();
        $valid = Hash::check($data['password'], $customer?->password ?? '$2y$12$'.str_repeat('x', 53));
        if (! $customer || ! $valid || ! $customer->canSignIn()) {
            foreach (array_keys($keys) as $key) {
                RateLimiter::hit($key, $limits['decay_seconds']);
            }
            throw ValidationException::withMessages(['email' => 'E-posta adresi veya parola hatalı.']);
        }

        RateLimiter::clear(array_key_first($keys));
        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();
        $customer->forceFill(['last_login_at' => now()])->save();
        $this->cart->mergeInto($request, $customer);
        $this->audit->log('auth.login', $customer, actorArea: Area::Customer, actorId: $customer->id);

        return redirect()->intended(route('store.account'));
    }

    public function showRegister(): View
    {
        return view('store.front.auth.register');
    }

    public function register(Request $request, CustomerService $customers): RedirectResponse
    {
        $customer = $customers->register($request->only(['first_name', 'last_name', 'email', 'phone', 'password', 'password_confirmation', 'kvkk', 'marketing']));

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $this->cart->mergeInto($request, $customer);

        return redirect()->intended(route('store.account'))->with('status', 'Üyeliğiniz oluşturuldu. Hoş geldiniz!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('store.home');
    }

    public function showForgot(): View
    {
        return view('store.front.auth.forgot');
    }

    public function sendReset(Request $request, AccountEmailService $accounts): RedirectResponse
    {
        $email = Str::lower(trim($request->validate(['email' => ['required', 'email', 'max:255']], [], ['email' => 'e-posta'])['email']));
        $key = 'customer-reset:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Çok fazla istek. Lütfen biraz sonra tekrar deneyin.']);
        }
        RateLimiter::hit($key, 900);

        $customer = StoreCustomer::where('email', $email)->first();
        if ($customer && $customer->canSignIn()) {
            $accounts->sendPasswordResetLink(Area::Customer, $customer);
        }

        return back()->with('status', 'Bu e-posta adresine kayıtlı bir üyelik varsa parola sıfırlama bağlantısı gönderildi.');
    }

    public function showReset(string $token, AuthTokenService $tokens): View|RedirectResponse
    {
        if (! $tokens->find(Area::Customer, 'password_reset', $token)) {
            return redirect()->route('store.password.request')->withErrors(['email' => 'Bağlantı geçersiz veya süresi dolmuş. Yeni bağlantı isteyin.']);
        }

        return view('store.front.auth.reset', ['token' => $token]);
    }

    public function reset(Request $request, AuthTokenService $tokens): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [], ['password' => 'parola']);

        $token = $tokens->find(Area::Customer, 'password_reset', $data['token']);
        if (! $token) {
            return redirect()->route('store.password.request')->withErrors(['email' => 'Bağlantı geçersiz veya süresi dolmuş. Yeni bağlantı isteyin.']);
        }

        $customer = StoreCustomer::findOrFail($token->user_id);
        DB::transaction(function () use ($tokens, $token, $customer, $data) {
            abort_unless($tokens->consume($token), 410);
            $customer->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        });
        $this->audit->log('auth.password_reset', $customer, actorArea: Area::Customer, actorId: $customer->id);

        return redirect()->route('store.login')->with('status', 'Parolanız güncellendi. Yeni parolanızla giriş yapabilirsiniz.');
    }
}
