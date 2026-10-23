<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Inventory\StockAlerts;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Tükenen ürün için "stoğa gelince haber ver" kaydı. */
class StockAlertController extends Controller
{
    public function store(Request $request, StockAlerts $alerts): RedirectResponse
    {
        $variant = $alerts->subscribe($request->only(['variant_id', 'email']), $request->user('customer'));

        return redirect()->to(route('store.product', $variant->product->slug).'#stok-bildirimi')->with('stock_alert', true);
    }
}
