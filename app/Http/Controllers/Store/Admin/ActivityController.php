<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Admins\ActivityLog;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mağaza işlem geçmişi: mağaza yöneticilerinin her işlemi ve platformun mağaza hesaplarına yaptığı
 * değişiklikler ("store.*"). Kayıtları yalnızca süper admin silebilir (seçilenler veya tamamı).
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

    /** Süper admin: seçilen kayıtları siler. */
    public function destroyMany(Request $request, ActivityLog $activity): RedirectResponse
    {
        $ids = $request->validate(['logs' => ['required', 'array', 'max:100'], 'logs.*' => ['integer']], [], ['logs' => 'kayıtlar'])['logs'];

        return back()->with('status', $activity->deleteMany($ids).' kayıt silindi.');
    }

    /** Süper admin: işlem geçmişinin tamamını siler. */
    public function destroyAll(Request $request, ActivityLog $activity): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Tümünü silmek için onay kutusunu işaretleyin.']);

        return redirect()->route('store_admin.activity')->with('status', $activity->deleteAll().' kayıt silindi.');
    }

    public static function query(): Builder
    {
        return ActivityLog::query();
    }

    /** @return array<int, string> mağaza yöneticisi id => ad */
    public static function adminNames(): array
    {
        return StoreAdmin::pluck('name', 'id')->all();
    }
}
