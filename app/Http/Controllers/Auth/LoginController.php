<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Auth\LoginCode;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RequirePlatformTwoFactor;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Giriş alanlarının giriş/çıkış işlemleri. Alan, isteğin URL önekinden (mağaza yönetiminde alan adından) belirlenir.
 * Toptancı, bayi ve mağaza yönetiminde paroladan sonra e-postaya giden kod istenir (LoginCode); platformda doğrulama uygulaması.
 */
class LoginController extends Controller
{
    public function __construct(private AuditLogger $audit, private LoginCode $codes) {}

    public function show(Request $request): View
    {
        return view('auth.login', ['area' => $this->area($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $area = $this->area($request);
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $email = Str::lower(trim($data['email']));

        $this->ensureNotRateLimited($area, $email, $request);

        $model = $area->model();
        $user = $model::where('email', $email)->first();

        // Kullanıcı yoksa da hash karşılaştırması yapılır; yanıt süresinden hesap varlığı anlaşılmasın.
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$'.str_repeat('x', 53));

        if (! $user || ! $user->password || ! $valid) {
            $this->hit($area, $email, $request);
            $this->audit->log('auth.login_failed', null, [], ['area' => $area->value, 'email' => $email], actorArea: $area);

            throw ValidationException::withMessages(['email' => 'E-posta adresi veya parola hatalı.']);
        }

        if (! $user->canSignIn()) {
            $message = ($user->getAttributes()['status'] ?? null) === 'pending_activation'
                ? 'Hesabınız henüz etkinleştirilmedi. E-postanızdaki aktivasyon bağlantısını kullanın.'
                : 'Hesabınız şu anda kullanıma kapalı. Lütfen yöneticinizle iletişime geçin.';

            throw ValidationException::withMessages(['email' => $message]);
        }

        RateLimiter::clear($this->emailKey($area, $email, $request));

        // Toptancı, bayi ve mağaza yönetimi: oturum, e-postaya giden 4 haneli kod girilince açılır.
        if (LoginCode::appliesTo($area)) {
            try {
                $this->codes->start($area, $user, $request->session());
            } catch (DomainException $e) {
                throw ValidationException::withMessages(['email' => $e->getMessage()]);
            }

            return redirect()->route($area->value.'.login.code');
        }

        return $this->complete($request, $area, $user);
    }

    /** Kod ekranı: e-postaya giden kodun girildiği yer. Bekleyen giriş yoksa giriş sayfasına döner. */
    public function code(Request $request): View|RedirectResponse
    {
        $area = $this->area($request);
        $pending = LoginCode::appliesTo($area) ? $this->codes->pending($area, $request->session()) : null;
        if (! $pending) {
            return redirect()->route($area->loginRoute());
        }

        return view('auth.login-code', ['area' => $area, 'email' => $pending['email'], 'resendIn' => max(0, $pending['sent_at'] + 60 - now()->timestamp)]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $area = $this->area($request);
        $data = $request->validate(['code' => ['required', 'digits:4']], ['code.digits' => 'Kod 4 rakamdan oluşur.'], ['code' => 'doğrulama kodu']);

        try {
            $userId = $this->codes->verify($area, $data['code'], $request->session());
        } catch (DomainException $e) {
            return $this->codes->pending($area, $request->session())
                ? back()->withErrors(['code' => $e->getMessage()])
                : redirect()->route($area->loginRoute())->withErrors(['email' => $e->getMessage()]);
        }

        $model = $area->model();
        $user = $model::find($userId);
        if (! $user || ! $user->canSignIn()) {
            return redirect()->route($area->loginRoute())->withErrors(['email' => 'Hesabınız şu anda kullanıma kapalı. Lütfen yöneticinizle iletişime geçin.']);
        }

        return $this->complete($request, $area, $user);
    }

    public function resendCode(Request $request): RedirectResponse
    {
        $area = $this->area($request);
        $pending = $this->codes->pending($area, $request->session());
        $model = $area->model();
        $user = $pending ? $model::find($pending['user_id']) : null;
        if (! $user) {
            return redirect()->route($area->loginRoute());
        }

        try {
            $this->codes->resend($area, $user, $request->session());
        } catch (DomainException $e) {
            return $this->codes->pending($area, $request->session())
                ? back()->withErrors(['code' => $e->getMessage()])
                : redirect()->route($area->loginRoute())->withErrors(['email' => $e->getMessage()]);
        }

        return back()->with('status', 'Yeni kod e-postanıza gönderildi.');
    }

    /** Oturumu açar (parola ve gerekiyorsa e-posta kodu doğrulandıktan sonra). */
    private function complete(Request $request, Area $area, Model $user): RedirectResponse
    {
        Auth::guard($area->guard())->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->log('auth.login', $user, actorArea: $area, actorId: $user->id,
            businessId: $area === Area::Business ? $user->business_id : null);

        if ($area === Area::Platform) {
            $request->session()->forget(RequirePlatformTwoFactor::SESSION_KEY);

            return redirect()->route($user->hasTwoFactorEnabled() ? 'platform.two-factor.challenge' : 'platform.two-factor.setup');
        }

        return $this->redirectIntended($request, $area);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $area = $this->area($request);

        Auth::guard($area->guard())->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($area->loginRoute());
    }

    private function area(Request $request): Area
    {
        return Area::fromRequest($request) ?? abort(404);
    }

    /** Giriş sonrası yalnızca aynı alanın içindeki bir adrese dönülür (açık yönlendirme engeli). */
    private function redirectIntended(Request $request, Area $area): RedirectResponse
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $prefix = url($area->prefix()).'/';

        if ($intended !== '' && str_starts_with($intended, $prefix)) {
            return redirect()->to($intended);
        }

        return redirect()->route($area->homeRoute());
    }

    private function ensureNotRateLimited(Area $area, string $email, Request $request): void
    {
        $limits = config('b2b.login_limits');

        foreach ([[$this->emailKey($area, $email, $request), $limits['per_email']], [$this->ipKey($area, $request), $limits['per_ip']]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

                throw ValidationException::withMessages([
                    'email' => "Çok fazla başarısız deneme. Lütfen {$minutes} dakika sonra tekrar deneyin.",
                ]);
            }
        }
    }

    private function hit(Area $area, string $email, Request $request): void
    {
        $decay = config('b2b.login_limits.decay_seconds');
        RateLimiter::hit($this->emailKey($area, $email, $request), $decay);
        RateLimiter::hit($this->ipKey($area, $request), $decay);
    }

    private function emailKey(Area $area, string $email, Request $request): string
    {
        return "login:{$area->value}:".sha1($email.'|'.$request->ip());
    }

    private function ipKey(Area $area, Request $request): string
    {
        return "login-ip:{$area->value}:".$request->ip();
    }
}
