<?php

namespace App\Http\Controllers\Business;

use App\Domain\Notifications\Notifier;
use App\Domain\Payments\OrderPaymentService;
use App\Domain\Support\LocalTime;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPayment;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Havale hesaba geçtiğinde veya teslimde ödeme alındığında toptancının ödeme kaydı (cariye tahsilat yazılır). */
class OrderPaymentController extends Controller
{
    public function store(Request $request, Order $order, OrderPaymentService $payments, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(OrderPayment::MANUAL_METHODS))],
            'paid_on' => ['required', 'date', 'before_or_equal:'.LocalTime::today()],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], ['method' => 'ödeme yöntemi', 'paid_on' => 'ödeme tarihi']);

        try {
            $order = $payments->recordManual($order, $data['method'], $data['paid_on'], $data['note'] ?? null, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }
        $notifier->orderPaid($order->fresh('relationship.dealer'), OrderPayment::MANUAL_METHODS[$data['method']]);

        return back()->with('status', 'Ödeme kaydedildi ve bayinin carisine tahsilat olarak işlendi.');
    }
}
