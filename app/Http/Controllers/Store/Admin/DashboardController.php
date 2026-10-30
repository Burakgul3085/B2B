<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Reports\StoreReports;
use App\Domain\Store\Visitors\VisitorCounter;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreReturn;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Mağaza yönetimi ana sayfası: ziyaretçiler, satış özeti, bekleyen işler, stok ve son işlemler. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, StoreReports $reports, VisitorCounter $visitors): View
    {
        $today = now('Europe/Istanbul')->toDateString();
        $monthStart = now('Europe/Istanbul')->startOfMonth()->toDateString();

        return view('store.admin.dashboard', [
            'today' => $reports->summary($today, $today),
            'month' => $reports->summary($monthStart, $today),
            'visitors' => $visitors->stats(),
            'waiting' => [
                ['Onay bekleyen (kapıda ödeme)', StoreOrder::where('status', 'awaiting_approval')->count(), route('store_admin.orders.index', ['status' => 'new'])],
                ['Havale bekleyen', StoreOrder::where('status', 'awaiting_transfer')->count(), route('store_admin.orders.index', ['status' => 'awaiting_transfer'])],
                ['Hazırlanacak (ödendi)', StoreOrder::where('status', 'paid')->count(), route('store_admin.orders.index', ['status' => 'new'])],
                ['Yola çıkacak (hazırlanıyor)', StoreOrder::where('status', 'preparing')->count(), route('store_admin.orders.index', ['status' => 'preparing'])],
                ['Yolda', StoreOrder::where('status', 'shipped')->count(), route('store_admin.orders.index', ['status' => 'shipped'])],
                ['Yeni iade talebi', StoreReturn::where('status', 'requested')->count(), route('store_admin.returns.index', ['status' => 'requested'])],
                ['Kargodaki iade', StoreReturn::where('status', 'shipped')->count(), route('store_admin.returns.index', ['status' => 'shipped'])],
                ['Para iadesi bekleyen', StoreReturn::where('status', 'received')->count(), route('store_admin.returns.index', ['status' => 'received'])],
            ],
            'admin' => $request->user('store_admin'),
            'activeAdmins' => StoreAdmin::where('is_active', true)->count(),
            'stats' => [
                'products' => StoreProduct::count(),
                'published' => StoreProduct::where('is_published', true)->count(),
                'critical' => StoreProductVariant::whereNotNull('critical_stock')->whereColumn('stock_quantity', '<=', 'critical_stock')->count(),
                'out' => StoreProductVariant::where('is_active', true)->where('stock_quantity', 0)->count(),
            ],
            'recent' => ActivityController::query()->latest('id')->limit(8)->get(),
            'names' => ActivityController::adminNames(),
        ]);
    }
}
