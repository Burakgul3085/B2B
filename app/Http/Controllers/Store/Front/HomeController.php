<?php

namespace App\Http\Controllers\Store\Front;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Mağaza ana sayfası. Vitrin M4'te gelir; o zamana kadar "yakında" sayfası gösterilir ve arama motorlarına kapalıdır. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('store.front.coming-soon');
    }
}
