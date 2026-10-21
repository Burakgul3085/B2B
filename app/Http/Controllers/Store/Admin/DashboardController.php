<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Mağaza yönetimi ana sayfası. Mağaza modülleri sonraki aşamalarda (M2–M8) buraya eklenir. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('store.admin.dashboard', [
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
