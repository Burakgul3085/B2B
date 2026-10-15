<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Yeni açılan toptancı ve bayi hesaplarının ilk parolasını kullanıcının kendisi belirler.
 * Parola hiçbir zaman e-postayla gönderilmez.
 */
class ActivationController extends Controller
{
    public function __construct(private AuthTokenService $tokens, private AuditLogger $audit) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $area = $this->area($request);

        if (! $this->tokens->find($area, 'activation', $token)) {
            return redirect()->route($area->loginRoute())
                ->withErrors(['email' => 'Aktivasyon bağlantısı geçersiz veya süresi dolmuş. Yeni bağlantı için işletmenizle iletişime geçin.']);
        }

        return view('auth.set-password', [
            'area' => $area,
            'token' => $token,
            'action' => route($area->value.'.activation.store'),
            'title' => 'Hesabınızı etkinleştirin',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $area = $this->area($request);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $token = $this->tokens->find($area, 'activation', $data['token']);
        if (! $token) {
            return redirect()->route($area->loginRoute())->withErrors(['email' => 'Aktivasyon bağlantısı geçersiz veya süresi dolmuş.']);
        }

        $model = $area->model();
        $user = $model::findOrFail($token->user_id);

        DB::transaction(function () use ($token, $user, $data) {
            abort_unless($this->tokens->consume($token), 410);
            $user->forceFill([
                'password' => $data['password'],
                'status' => $user->status === 'pending_activation' ? 'active' : $user->status,
                'activated_at' => $user->activated_at ?? now(),
            ])->save();
        });

        $this->audit->log('auth.activated', $user, actorArea: $area, actorId: $user->id,
            businessId: $area === Area::Business ? $user->business_id : null);

        return redirect()->route($area->loginRoute())->with('status', 'Hesabınız etkinleştirildi. Belirlediğiniz parolayla giriş yapabilirsiniz.');
    }

    private function area(Request $request): Area
    {
        $area = Area::fromRequest($request);
        abort_unless(in_array($area, [Area::Business, Area::Dealer], true), 404);

        return $area;
    }
}
