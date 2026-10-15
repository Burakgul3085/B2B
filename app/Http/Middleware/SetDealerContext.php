<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\DealerUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bayi paneli: oturumda seçili toptancı ilişkisini her istekte yeniden doğrular ve
 * işletme bağlamını o ilişkiden kurar. İlişki askıya alınırsa bayi hemen erişimini kaybeder.
 */
class SetDealerContext
{
    public const SESSION_KEY = 'dealer.relationship_id';

    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var DealerUser $user */
        $user = Auth::guard('dealer')->user();

        if (! $user->canSignIn()) {
            Auth::guard('dealer')->logout();
            $request->session()->invalidate();

            return redirect()->route('dealer.login')
                ->withErrors(['email' => 'Hesabınız şu anda kullanıma kapalı.']);
        }

        $relationships = $user->usableRelationships();
        $selectedId = (int) $request->session()->get(self::SESSION_KEY);
        $selected = $relationships->firstWhere('id', $selectedId);

        if ($selected === null) {
            $request->session()->forget(self::SESSION_KEY);

            if ($relationships->count() === 1) {
                $selected = $relationships->first();
                $request->session()->put(self::SESSION_KEY, $selected->id);
            } elseif (! $request->routeIs('dealer.select-business*')) {
                return redirect()->route('dealer.select-business');
            }
        }

        if ($selected !== null) {
            $this->tenant->set($selected->business_id, $selected->id);
        }

        view()->share('dealerRelationships', $relationships);
        view()->share('currentRelationship', $selected);
        view()->share('currentBusiness', $selected?->business);

        return $next($request);
    }
}
