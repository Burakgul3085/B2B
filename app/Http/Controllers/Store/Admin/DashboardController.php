<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store\StoreAdmin;
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
            'recent' => ActivityController::query()->latest('id')->limit(8)->get(),
            'names' => ActivityController::adminNames(),
        ]);
    }
}
