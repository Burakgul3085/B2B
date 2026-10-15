<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\DealerRelationship;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Toptancı dashboard'u. Bu aşamada yalnızca mevcut modüllerin gerçek verisi gösterilir;
 * sipariş, stok ve cari KPI'ları ilgili modüllerle birlikte eklenecek (sahte rakam yok).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $business = $request->user()->business;

        $kpis = [
            'active_dealers' => DealerRelationship::where('status', 'active')->count(),
        ];

        $setup = [
            ['label' => 'İşletme bilgilerini tamamlayın', 'done' => filled($business->phone) && filled($business->address), 'route' => 'business.settings.edit'],
            ['label' => 'Panel parolanızı ve güvenliğinizi kontrol edin', 'done' => true, 'route' => 'business.account'],
        ];

        return view('business.dashboard', compact('kpis', 'setup'));
    }
}
