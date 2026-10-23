<?php

namespace App\Http\Controllers\Store\Front;

use App\Http\Controllers\Controller;
use App\Models\Store\StoreBrand;
use Illuminate\View\View;

/** Markalar sayfası. (Hakkımızda ve İletişim: SitePageController) */
class PageController extends Controller
{
    public function brands(): View
    {
        $brands = StoreBrand::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)->whereHas('activeVariants')])
            ->orderBy('name')->get()
            ->filter(fn ($b) => $b->products_count > 0);

        return view('store.front.brands', ['brands' => $brands]);
    }
}
