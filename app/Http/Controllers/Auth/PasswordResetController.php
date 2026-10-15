<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Domain\Mail\SystemMailer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** "Parolamı unuttum" ve parola belirleme — üç alan için ayrı bağlantılar. */
class PasswordResetController extends Controller
{
    public function __construct(
        private AuthTokenService $tokens,
        private SystemMailer $mailer,
        private AuditLogger $audit,
    ) {}

    public function request(Request $request): View
    {
        return view('auth.forgot-password', ['area' => $this->area($request)]);
    }

    public function email(Request $request): RedirectResponse
    {
        $area = $this->area($request);
        $email = Str::lower(trim($request->validate(['email' => ['required', 'email', 'max:255']])['email']));

        $key = "pw-reset:{$area->value}:".$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Çok fazla istek. Lütfen biraz sonra tekrar deneyin.']);
        }
        RateLimiter::hit($key, 900);

        $model = $area->model();
        $user = $model::where('email', $email)->first();

        // Yalnızca etkin hesaplara bağlantı gönderilir; yanıt her durumda aynıdır (hesap varlığı sızdırılmaz).
        if ($user && $user->password && $user->canSignIn()) {
            $plain = $this->tokens->issue($area, $user->id, 'password_reset');
            $this->mailer->send($user->email, 'Parola sıfırlama', [
                'title' => 'Parolanızı sıfırlayın',
                'intro' => "{$area->label()} hesabınız için parola sıfırlama talebi aldık. Bağlantı ".config('b2b.password_reset_ttl_minutes').' dakika geçerlidir.',
                'action_url' => $this->tokens->url($area, 'password_reset', $plain),
                'action_label' => 'Yeni parola belirle',
                'outro' => 'Bu talebi siz yapmadıysanız bu e-postayı dikkate almayın; parolanız değişmez.',
            ]);
        }

        return back()->with('status', 'Bu e-posta adresine kayıtlı etkin bir hesap varsa sıfırlama bağlantısı gönderildi.');
    }

    public function edit(Request $request, string $token): View|RedirectResponse
    {
        $area = $this->area($request);

        if (! $this->tokens->find($area, 'password_reset', $token)) {
            return redirect()->route($area->loginRoute())->withErrors(['email' => 'Bağlantı geçersiz veya süresi dolmuş.']);
        }

        return view('auth.set-password', [
            'area' => $area,
            'token' => $token,
            'action' => route($area->value.'.password.update'),
            'title' => 'Yeni parola belirleyin',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $area = $this->area($request);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $token = $this->tokens->find($area, 'password_reset', $data['token']);
        if (! $token) {
            return redirect()->route($area->loginRoute())->withErrors(['email' => 'Bağlantı geçersiz veya süresi dolmuş.']);
        }

        $model = $area->model();
        $user = $model::findOrFail($token->user_id);

        DB::transaction(function () use ($token, $user, $data) {
            abort_unless($this->tokens->consume($token), 410);
            $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        });

        $this->audit->log('auth.password_reset', $user, actorArea: $area, actorId: $user->id,
            businessId: $area === Area::Business ? $user->business_id : null);

        return redirect()->route($area->loginRoute())->with('status', 'Parolanız güncellendi. Yeni parolanızla giriş yapabilirsiniz.');
    }

    private function area(Request $request): Area
    {
        return Area::fromRequest($request) ?? abort(404);
    }
}
