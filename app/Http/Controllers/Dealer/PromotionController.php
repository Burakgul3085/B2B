<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Pricing\PromotionService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Category;
use Illuminate\View\View;

/** Bayinin Kampanyalar sayfası: kendisine açık kupon ve kampanyalar (seçim kuralları: PromotionService::visibleFor). */
class PromotionController extends Controller
{
    use UsesDealerContext;

    public function index(PromotionService $promotions): View
    {
        $rows = $promotions->visibleFor($this->relationship());
        $categoryIds = $rows->flatMap(fn (array $row) => $row['promotion']->category_ids ?? [])->unique()->values()->all();

        return view('dealer.promotions', [
            'rows' => $rows,
            'categories' => $categoryIds ? Category::whereIn('id', $categoryIds)->pluck('name', 'id') : collect(),
        ]);
    }
}
