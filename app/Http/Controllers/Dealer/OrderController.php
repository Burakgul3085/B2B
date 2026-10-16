<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Notifications\Notifier;
use App\Domain\Ordering\OrderService;
use App\Domain\Ordering\OrderStateMachine;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Order;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    use UsesDealerContext;

    public function __construct(private OrderService $orders) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...array_keys(Order::STATUSES), 'open'])],
            'q' => ['nullable', 'string', 'max:40'],
        ]);

        $orders = Order::where('dealer_relationship_id', $this->relationship()->id)
            ->withCount('items')
            ->when(($filters['status'] ?? null) === 'open', fn ($q) => $q->whereIn('status', [...Order::PENDING, ...Order::ACTIVE_FULFILMENT]))
            ->when(($filters['status'] ?? null) && $filters['status'] !== 'open', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where('order_no', 'like', '%'.addcslashes($t, '%_\\').'%'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('dealer.orders.index', compact('orders', 'filters'));
    }

    public function show(Order $order): View
    {
        $this->assertOwn($order);
        $order->load(['items', 'history']);

        return view('dealer.orders.show', [
            'order' => $order,
            'canCancel' => in_array($order->status, Order::PENDING, true),
            'canRequestCancel' => OrderStateMachine::can($order->status, 'cancel_requested'),
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->assertOwn($order);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'iptal nedeni'])['reason'];

        try {
            $order = $this->orders->cancelByDealer($order, $reason);
        } catch (DomainException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        $notifier = app(Notifier::class);
        $order->status === 'cancelled' ? $notifier->orderCancelledByDealer($order) : $notifier->cancelRequested($order);

        return back()->with('status', $order->status === 'cancelled' ? 'Siparişiniz iptal edildi.' : 'İptal talebiniz toptancıya iletildi.');
    }

    private function assertOwn(Order $order): void
    {
        abort_unless($order->dealer_relationship_id === $this->relationship()->id, 404);
    }
}
