<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Bayi dashboard'u. Sipariş, cari ve mesaj özetleri ilgili modüllerle eklenecek;
 * o zamana kadar sahte rakam yerine bilgilendirici boş durum gösterilir.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dealer.dashboard');
    }
}
