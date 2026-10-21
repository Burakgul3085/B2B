<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store\StoreAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mağaza işlem geçmişi: mağaza yöneticilerinin her işlemi ve platformun mağaza hesaplarına yaptığı
 * değişiklikler ("store.*"). Kayıtlar yalnızca eklenir.
 */
class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'admin' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = self::query()
            ->when($filters['admin'] ?? null, fn ($q, $id) => $q->where('actor_type', 'store_admin')->where('actor_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('action', 'like', "%{$term}%"))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('store.admin.activity', [
            'logs' => $logs,
            'filters' => $filters,
            'admins' => StoreAdmin::orderBy('name')->get(['id', 'name']),
            'names' => self::adminNames(),
        ]);
    }

    public static function query(): Builder
    {
        return AuditLog::query()->where(fn ($q) => $q
            ->where('actor_type', 'store_admin')
            ->orWhere('action', 'like', 'store.%'));
    }

    /** @return array<int, string> mağaza yöneticisi id => ad */
    public static function adminNames(): array
    {
        return StoreAdmin::pluck('name', 'id')->all();
    }
}
