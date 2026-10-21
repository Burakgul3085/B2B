<?php

namespace App\Http\Controllers\Store\Front;

use App\Http\Controllers\Controller;
use App\Models\Store\StoreBrand;
use App\Models\Store\StorePage;
use Illuminate\View\View;

/** Sabit sayfalar ve Markalar sayfası. */
class PageController extends Controller
{
    public function show(string $slug): View
    {
        return view('store.front.page', ['page' => StorePage::where('slug', $slug)->where('is_active', true)->firstOrFail()]);
    }

    public function brands(): View
    {
        $brands = StoreBrand::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)->whereHas('activeVariants')])
            ->orderBy('name')->get()
            ->filter(fn ($b) => $b->products_count > 0);

        return view('store.front.brands', ['brands' => $brands]);
    }
}
