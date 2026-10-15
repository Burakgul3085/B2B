<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\BusinessModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Platform yöneticisinin işletme için kapattığı modüle erişimi engeller. */
class EnsureModuleEnabled
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $disabled = BusinessModule::where('business_id', $this->tenant->businessId())
            ->where('module_key', $module)->where('enabled', false)->exists();

        abort_if($disabled, 403, 'Bu modül işletmeniz için etkin değil.');

        return $next($request);
    }
}
