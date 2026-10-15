<?php

namespace App\Http\Controllers\Business;

use App\Domain\Ordering\ReturnService;
use App\Domain\Support\Money;
use App\Http\Controllers\Controller;
use App\Models\Order;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderReturnController extends Controller
{
    public function __construct(private ReturnService $returns) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['note' => 'iade açıklaması']);

        try {
            $return = $this->returns->create($order, $data['qty'], $data['note'] ?? null, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['return' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'İade kaydedildi: stok girişi yapıldı ve bayinin carisine '.Money::format($return->grand_total).' alacak yazıldı.');
    }
}
