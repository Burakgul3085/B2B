<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform denetim kayıtları: yalnızca platform yöneticilerinin işlemleri ve
 * platform girişleri. İşletmelerin kendi ticari işlem kayıtları burada listelenmez.
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'business_id' => ['nullable', 'integer'],
        ]);

        $logs = AuditLog::query()
            ->where('actor_type', 'platform')
            ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
            ->when($filters['business_id'] ?? null, fn ($q, $id) => $q->where('business_id', $id))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $businesses = Business::orderBy('display_name')->pluck('display_name', 'id');

        return view('platform.audit-logs', compact('logs', 'filters', 'businesses'));
    }
}
