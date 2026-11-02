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

        $toCard = (string) $return->refunded_to_card;
        $toAccount = bcsub((string) $return->grand_total, $toCard, 2);

        return back()->with('status', 'İade kaydedildi, stok girişi yapıldı.'
            .(bccomp($toCard, '0', 2) > 0 ? ' '.Money::format($toCard)." iyzico'dan bayinin kartına iade edildi." : '')
            .(bccomp($toAccount, '0', 2) > 0 ? ' Bayinin carisine '.Money::format($toAccount).' alacak yazıldı.' : ''));
    }
}
