<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\License;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $statusCounts = Business::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $expiring = License::query()
            ->join('businesses', 'businesses.id', '=', 'licenses.business_id')
            ->whereNotNull('licenses.ends_on')
            ->whereBetween('licenses.ends_on', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('licenses.ends_on')
            ->limit(10)
            ->get(['licenses.*', 'businesses.display_name']);

        $pendingOwners = Business::query()
            ->whereHas('user', fn ($q) => $q->where('status', 'pending_activation'))
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        $recent = AuditLog::query()->where('actor_type', 'platform')->latest('id')->limit(10)->get();

        return view('platform.dashboard', compact('statusCounts', 'expiring', 'pendingOwners', 'recent'));
    }
}
