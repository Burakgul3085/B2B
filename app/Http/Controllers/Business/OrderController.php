<?php

namespace App\Http\Controllers\Business;

use App\Domain\Inventory\InsufficientStock;
use App\Domain\Notifications\Notifier;
use App\Domain\Ordering\OrderService;
use App\Domain\Ordering\OrderStateMachine;
use App\Domain\Ordering\ReturnService;
use App\Http\Controllers\Controller;
use App\Models\DealerRelationship;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Rules\NotBefore;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private Notifier $notifier) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...array_keys(Order::STATUSES), 'pending'])],
            'dealer' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', new NotBefore('from')],
        ]);
        $status = $filters['status'] ?? 'pending';

        $orders = Order::query()->with('relationship.dealer:id,legal_name')->withCount('items')
            ->when($status === 'pending', fn ($q) => $q->whereIn('status', [...Order::PENDING, 'cancel_requested']))
            ->when($status !== 'pending', fn ($q) => $q->where('status', $status))
            ->when($filters['dealer'] ?? null, fn ($q, $d) => $q->where('dealer_relationship_id', $d))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where('order_no', 'like', '%'.addcslashes($t, '%_\\').'%'))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('business.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'filters' => $filters,
            'counts' => Order::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'dealers' => DealerRelationship::with('dealer:id,legal_name')->orderBy('dealer_code')->get()->mapWithKeys(fn ($r) => [$r->id => $r->dealer_code.' · '.$r->dealer->legal_name]),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['items.product.unit', 'history', 'relationship.dealer', 'warehouse']);
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        // Onay ekranında her kalem için seçili depodaki güncel stok (bilgi amaçlı; kesin kontrol onayda kilitle yapılır).
        $stock = WarehouseStock::whereIn('product_id', $order->items->pluck('product_id'))->get()
            ->groupBy('warehouse_id')->map(fn ($rows) => $rows->pluck('quantity', 'product_id'));

        return view('business.orders.show', [
            'order' => $order,
            'warehouses' => $warehouses,
            'stock' => $stock,
            'canApprove' => in_array($order->status, Order::PENDING, true),
            'returnable' => $order->status === 'completed' ? ReturnService::returnable($order) : [],
            'returns' => OrderReturn::where('order_id', $order->id)->with('items')->latest('id')->get(),
            'can' => fn (string $to) => OrderStateMachine::can($order->status, $to),
        ]);
    }

    public function review(Order $order): RedirectResponse
    {
        return $this->run($order, fn () => $this->orders->markInReview($order), 'Sipariş incelemeye alındı.');
    }

    public function approve(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'qty' => ['required', 'array'],
            'qty.*' => ['required', 'string', 'max:20'],
            'delivery_recipient' => ['nullable', 'string', 'max:120'],
            'delivery_phone' => ['nullable', 'string', 'max:30'],
            'delivery_note' => ['nullable', 'string', 'max:500'],
            'delivery_planned_on' => ['nullable', 'date', 'after_or_equal:today'],
        ], [], ['warehouse_id' => 'depo', 'delivery_planned_on' => 'planlanan teslim tarihi']);

        try {
            $order = $this->orders->approve($order, $data['qty'], (int) $data['warehouse_id'], [
                'recipient' => $data['delivery_recipient'] ?? null,
                'phone' => $data['delivery_phone'] ?? null,
                'note' => $data['delivery_note'] ?? null,
                'planned_on' => $data['delivery_planned_on'] ?? null,
            ], $request->user()->id);
        } catch (InsufficientStock $e) {
            return back()->withErrors(['order' => $e->getMessage().' Miktarı düşürerek kısmi onaylayabilir veya siparişi reddedebilirsiniz.'])->withInput();
        } catch (DomainException $e) {
            return back()->withErrors(['order' => $e->getMessage()])->withInput();
        }

        $this->notifier->orderStatusChanged($order->fresh(['relationship']), $order->is_partial ? 'Bazı kalemler stok nedeniyle kısmi karşılandı.' : null);

        return back()->with('status', $order->is_partial ? 'Sipariş kısmi olarak onaylandı; stok düşüldü.' : 'Sipariş onaylandı; stok düşüldü.');
    }

    public function reject(Request $request, Order $order): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'ret gerekçesi'])['reason'];

        return $this->run($order, fn () => $this->orders->reject($order, $reason), 'Sipariş reddedildi.');
    }

    public function prepare(Order $order): RedirectResponse
    {
        return $this->run($order, fn () => $this->orders->markPreparing($order), 'Sipariş "Hazırlanıyor" olarak işaretlendi.');
    }

    public function complete(Order $order): RedirectResponse
    {
        return $this->run($order, fn () => $this->orders->complete($order), 'Sipariş tamamlandı.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'iptal nedeni'])['reason'];

        return $this->run($order, fn () => $this->orders->cancelByBusiness($order, $reason), 'Sipariş iptal edildi. Düşülen stok ve cari borç ters kayıtla geri alındı.');
    }

    public function resolveCancel(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->run($order, fn () => $this->orders->resolveCancelRequest($order, $data['decision'] === 'accept', $data['note'] ?? null),
            $data['decision'] === 'accept' ? 'İptal talebi kabul edildi; ters kayıtlar yazıldı.' : 'İptal talebi reddedildi; sipariş önceki durumuna döndü.',
            $data['decision'] === 'decline' ? 'İptal talebiniz kabul edilmedi.'.(($data['note'] ?? null) ? ' Not: '.$data['note'] : '') : null);
    }

    /** İşlemi çalıştırır; başarılıysa (commit sonrası) bayiye bildirim ve e-posta gönderir. */
    private function run(Order $order, callable $action, string $message, ?string $note = null): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        $this->notifier->orderStatusChanged($order->fresh(['relationship']), $note);

        return back()->with('status', $message);
    }
}
