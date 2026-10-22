<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\ErrorEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hata kayıtları: açık / çözülmüş hatalar, tekrar sayısı; çözüldü işaretleme ve eski çözülmüşleri temizleme. */
class ErrorEventController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['durum' => ['nullable', 'in:acik,cozuldu'], 'q' => ['nullable', 'string', 'max:100']]);
        $resolved = ($filters['durum'] ?? 'acik') === 'cozuldu';

        return view('platform.errors', [
            'errors_list' => ErrorEvent::query()
                ->when($resolved, fn ($q) => $q->whereNotNull('resolved_at'), fn ($q) => $q->whereNull('resolved_at'))
                // Kullanıcının hata sayfasında gördüğü referans kodu (H12) doğrudan aranabilir.
                ->when($filters['q'] ?? null, fn ($q, $t) => preg_match('/^H(\d+)$/i', trim($t), $m)
                    ? $q->whereKey((int) $m[1])
                    : $q->where(fn ($q) => $q->where('message', 'like', "%{$t}%")
                        ->orWhere('exception_class', 'like', "%{$t}%")->orWhere('location', 'like', "%{$t}%")))
                ->orderByDesc('last_seen_at')->paginate(30)->withQueryString(),
            'filters' => $filters + ['durum' => $resolved ? 'cozuldu' : 'acik'],
            'counts' => ['acik' => ErrorEvent::whereNull('resolved_at')->count(), 'cozuldu' => ErrorEvent::whereNotNull('resolved_at')->count()],
            'last24' => ErrorEvent::where('last_seen_at', '>=', now()->subDay())->count(),
        ]);
    }

    public function resolve(ErrorEvent $errorEvent, AuditLogger $audit): RedirectResponse
    {
        $errorEvent->update(['resolved_at' => now()]);
        $audit->log('platform.error_resolved', null, [], ['error' => $errorEvent->id, 'class' => $errorEvent->exception_class]);

        return back()->with('status', 'Hata çözüldü olarak işaretlendi. Tekrar oluşursa yeniden açılır.');
    }

    /** Son 30 günde tekrar etmemiş çözülmüş kayıtları siler. */
    public function purge(AuditLogger $audit): RedirectResponse
    {
        $count = ErrorEvent::whereNotNull('resolved_at')->where('last_seen_at', '<', now()->subDays(30))->delete();
        $audit->log('platform.errors_purged', null, [], ['count' => $count]);

        return back()->with('status', "{$count} eski kayıt silindi.");
    }
}
