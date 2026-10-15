<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\BusinessUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toptancı paneli: işletme bağlamını oturumdaki kullanıcıdan kurar.
 * İşletme askıya alınmış veya kullanıcı devre dışıysa oturum her istekte sonlandırılır.
 */
class SetBusinessContext
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var BusinessUser $user */
        $user = Auth::guard('business')->user();

        if (! $user->canSignIn()) {
            Auth::guard('business')->logout();
            $request->session()->invalidate();

            return redirect()->route('business.login')
                ->withErrors(['email' => 'Hesabınız veya işletmeniz şu anda kullanıma kapalı.']);
        }

        $this->tenant->set($user->business_id);
        view()->share('currentBusiness', $user->business);

        return $next($request);
    }
}
