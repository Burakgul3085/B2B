<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetDealerContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Birden fazla toptancıyla çalışan bayinin aktif toptancıyı seçmesi. */
class SelectBusinessController extends Controller
{
    public function show(Request $request): View
    {
        return view('dealer.select-business', ['relationships' => $request->user()->usableRelationships()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $id = (int) $request->validate(['relationship_id' => ['required', 'integer']])['relationship_id'];

        // Yalnızca bayinin kendi aktif ilişkilerinden biri seçilebilir.
        $relationship = $request->user()->usableRelationships()->firstWhere('id', $id);
        abort_if($relationship === null, 404);

        $request->session()->put(SetDealerContext::SESSION_KEY, $relationship->id);

        return redirect()->route('dealer.dashboard');
    }
}
