<?php

namespace App\Http\Controllers\Business;

use App\Domain\Platform\SupportAccess;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Models\SupportAccessGrant;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ayarlar → Destek Erişimi: platform yöneticisine süreli erişim ver, kaldır; yapılan görüntülemeleri izle. */
class SupportAccessController extends Controller
{
    public function __construct(private SupportAccess $support, private TenantContext $tenant) {}

    public function index(): View
    {
        $businessId = $this->tenant->businessId();

        return view('business.support-access', [
            'admins' => PlatformAdmin::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'grants' => SupportAccessGrant::with('admin:id,name')->where('business_id', $businessId)->latest('id')->limit(30)->get(),
            'views' => AuditLog::where('business_id', $businessId)->whereIn('action', ['support.viewed', 'support.granted', 'support.revoked'])
                ->latest('id')->limit(50)->get(),
            'adminNames' => PlatformAdmin::pluck('name', 'id'),
            'durations' => SupportAccess::DURATIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $this->support->grant($this->tenant->businessId(), $request->user()->id, $request->only(['platform_admin_id', 'hours', 'reason']));
        } catch (DomainException $e) {
            return back()->withErrors(['platform_admin_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Destek erişimi verildi. Süre dolduğunda kendiliğinden kapanır.');
    }

    public function destroy(int $grant): RedirectResponse
    {
        $this->support->revoke(SupportAccessGrant::where('business_id', $this->tenant->businessId())->findOrFail($grant));

        return back()->with('status', 'Destek erişimi kaldırıldı.');
    }
}
