<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Mail\SystemMailer;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use Illuminate\View\View;

/**
 * Platform: işletmelerin e-posta altyapısının teknik durumu. Yalnızca durum ve sayılar görünür;
 * e-posta içerikleri, alıcılar ve şifreler platform yöneticisine gösterilmez.
 */
class EmailStatusController extends Controller
{
    public function __invoke(): View
    {
        $settings = EmailSetting::withoutBusinessScope()->get()->keyBy('business_id');
        $stats = EmailLog::withoutBusinessScope()->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('business_id, status, count(*) c')->groupBy('business_id', 'status')->get()
            ->groupBy('business_id')->map(fn ($rows) => $rows->pluck('c', 'status'));

        return view('platform.email-status', [
            'businesses' => Business::orderBy('display_name')->get(['id', 'display_name', 'status']),
            'settings' => $settings,
            'stats' => $stats,
            'systemConfigured' => app(SystemMailer::class)->isConfigured(),
        ]);
    }
}
