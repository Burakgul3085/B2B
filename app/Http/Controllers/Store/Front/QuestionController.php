<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Reviews\QuestionService;
use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Ürün sayfasından soru sorma (üyelik gerekmez); soru cevaplanınca yayımlanır, cevap e-postayla gider. */
class QuestionController extends Controller
{
    public function store(Request $request, string $slug, Catalog $catalog, QuestionService $questions): RedirectResponse
    {
        $product = $catalog->products()->where('slug', $slug)->firstOrFail();
        $questions->ask($product, $request->only(['first_name', 'last_name', 'email', 'question', 'consent']), $request->user('customer'), $request->ip());

        return redirect()->to(route('store.product', $product->slug).'#sorular')->with('question_sent', true);
    }
}
