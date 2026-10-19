<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Notifications\Notifier;
use App\Domain\Ordering\OrderService;
use App\Domain\Ordering\OrderStateMachine;
use App\Domain\Ordering\ReorderService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\CurrentAccountMovement;
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

    /** Durum sekmeleri: birden çok durumu kapsayan gruplar (tek durum da filtre olarak kabul edilir). */
    public const GROUPS = [
        'open' => [...Order::PENDING, ...Order::ACTIVE_FULFILMENT],
        'pending' => Order::PENDING,
        'active' => Order::ACTIVE_FULFILMENT,
        'completed' => ['completed'],
        'closed' => ['rejected', 'cancelled'],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...array_keys(Order::STATUSES), ...array_keys(self::GROUPS)])],
            'q' => ['nullable', 'string', 'max:60'],
        ]);
        $status = $filters['status'] ?? null;
        $relationshipId = $this->relationship()->id;

        $orders = Order::where('dealer_relationship_id', $relationshipId)
            ->withCount('items')
            ->with(['items' => fn ($q) => $q->select('id', 'order_id', 'name')->orderBy('id')])
            ->when($status, fn ($q) => $q->whereIn('status', self::GROUPS[$status] ?? [$status]))
            ->when($filters['q'] ?? null, function ($q, $t) {
                $like = '%'.addcslashes($t, '%_\\').'%';
                $q->where(fn ($w) => $w->where('order_no', 'like', $like)->orWhereHas('items', fn ($i) => $i->where('name', 'like', $like)));
            })
            ->latest('created_at')->latest('id')
            ->paginate(20)
            ->withQueryString();

        $byStatus = Order::where('dealer_relationship_id', $relationshipId)
            ->selectRaw('status, COUNT(*) c, SUM(grand_total) t')->groupBy('status')->toBase()->get()->keyBy('status');
        $sum = fn (array $statuses, string $col) => $byStatus->only($statuses)->sum(fn ($r) => (float) $r->{$col});

        return view('dealer.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'counts' => ['' => (int) $byStatus->sum('c')] + array_map(fn ($s) => (int) $sum($s, 'c'), self::GROUPS),
            'stats' => [
                'open_count' => (int) $sum(self::GROUPS['open'], 'c'),
                'open_total' => $sum(self::GROUPS['open'], 't'),
                'month_total' => (float) Order::where('dealer_relationship_id', $relationshipId)->whereNotIn('status', self::GROUPS['closed'])
                    ->where('created_at', '>=', now()->startOfMonth())->sum('grand_total'),
                'completed_count' => (int) $sum(['completed'], 'c'),
            ],
        ]);
    }

    public function show(Order $order): View
    {
        $this->assertOwn($order);
        $order->load(['items.product.primaryImage', 'history']);

        return view('dealer.orders.show', [
            'order' => $order,
            'dueDate' => CurrentAccountMovement::where('order_id', $order->id)->where('type', 'order_debit')->whereNull('reverses_movement_id')->value('due_date'),
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

    public function reorder(Order $order, ReorderService $reorder): RedirectResponse
    {
        $this->assertOwn($order);
        $result = $reorder->reorder($this->relationship(), $order);

        if ($result['added'] === 0) {
            return back()->withErrors(['order' => 'Bu siparişteki ürünler şu an sepete eklenemiyor. '.implode(' ', $result['skipped'])]);
        }

        $redirect = redirect()->route('dealer.cart')->with('status', $result['added'].' ürün sepetinize eklendi. Fiyatlar güncel listenize göre hesaplandı.');

        return $result['skipped'] ? $redirect->withErrors(['reorder' => 'Eklenemeyen ürünler: '.implode(' · ', $result['skipped'])]) : $redirect;
    }

    private function assertOwn(Order $order): void
    {
        abort_unless($order->dealer_relationship_id === $this->relationship()->id, 404);
    }
}
