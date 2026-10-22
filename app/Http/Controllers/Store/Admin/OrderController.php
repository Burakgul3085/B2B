<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Orders\CodPolicy;
use App\Domain\Store\Orders\InvoiceService;
use App\Domain\Store\Orders\OrderService;
use App\Domain\Store\Returns\ReturnService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreCodBlock;
use App\Models\Store\StoreOrder;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Mağaza siparişleri: liste, ayrıntı, durum işlemleri (onay, hazırlama, yola çıkarma, teslim, iptal) ve fatura. */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_merge(array_keys(StoreOrder::STATUSES), ['new']))],
            'payment' => ['nullable', Rule::in(array_keys(StoreOrder::PAYMENT_METHODS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $orders = StoreOrder::query()
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('order_no', 'like', "%{$term}%")->orWhere('shipping_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $term).'%')))
            ->when($filters['status'] ?? null, fn ($q, $s) => $s === 'new' ? $q->whereIn('status', ['awaiting_approval', 'paid']) : $q->where('status', $s))
            ->when($filters['payment'] ?? null, fn ($q, $p) => $q->where('payment_method', $p))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<', date('Y-m-d', strtotime($d.' +1 day'))))
            ->withCount('items')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('store.admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'counts' => StoreOrder::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->all(),
        ]);
    }

    public function show(StoreOrder $storeOrder, ReturnService $returns): View
    {
        return view('store.admin.orders.show', [
            'order' => $storeOrder->load(['items', 'events', 'payments', 'consents', 'customer', 'returns', 'refunds', 'invoice', 'creditNotes']),
            'returnable' => $storeOrder->status === 'delivered' ? $returns->returnableQuantities($storeOrder) : [],
            'carriers' => StoreCarrier::where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id'),
            'blocked' => StoreCodBlock::where('phone', $storeOrder->phone)
                ->orWhere('address_key', CodPolicy::addressKey($storeOrder->shipping_district_id, $storeOrder->shipping_neighborhood, $storeOrder->shipping_address))->first(),
            'actors' => ActivityController::adminNames(),
        ]);
    }

    public function approve(StoreOrder $storeOrder): RedirectResponse
    {
        return $this->attempt(fn () => $this->orders->approve($storeOrder), 'Sipariş onaylandı.');
    }

    public function prepare(StoreOrder $storeOrder): RedirectResponse
    {
        return $this->attempt(fn () => $this->orders->prepare($storeOrder), 'Sipariş hazırlanıyor olarak işaretlendi.');
    }

    public function ship(Request $request, StoreOrder $storeOrder): RedirectResponse
    {
        $data = $request->validate(['carrier_id' => ['nullable', 'integer'], 'tracking_number' => ['nullable', 'string', 'max:60']]);

        return $this->attempt(fn () => $this->orders->ship($storeOrder, isset($data['carrier_id']) ? (int) $data['carrier_id'] : null, $data['tracking_number'] ?? null),
            'Sipariş yola çıktı; müşteriye e-posta gönderildi.');
    }

    public function deliver(Request $request, StoreOrder $storeOrder): RedirectResponse
    {
        return $this->attempt(fn () => $this->orders->deliver($storeOrder, $request->input('collected_method')), 'Sipariş teslim edildi.');
    }

    public function notDelivered(Request $request, StoreOrder $storeOrder): RedirectResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:200']], [], ['reason' => 'gerekçe'])['reason'];

        return $this->attempt(fn () => $this->orders->notDelivered($storeOrder, $reason), 'Teslim alınmadı olarak işaretlendi; ürünler stoğa döndü, telefon kapıda ödeme engel listesine eklendi.');
    }

    public function cancel(Request $request, StoreOrder $storeOrder): RedirectResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:200']], [], ['reason' => 'gerekçe'])['reason'];

        $paid = $storeOrder->payment_method === 'card' && $storeOrder->isSettled();

        return $this->attempt(fn () => $this->orders->cancel($storeOrder, $reason, $request->ip()),
            $paid ? 'Sipariş iptal edildi; ödeme karta iade edildi, ürünler stoğa döndü.' : 'Sipariş iptal edildi; ürünler stoğa döndü.');
    }

    public function storeInvoice(Request $request, StoreOrder $storeOrder, InvoiceService $invoices): RedirectResponse
    {
        return $this->attempt(fn () => $invoices->attach($storeOrder, $request->only(['invoice_no', 'issued_on']), $request->file('file')), 'Fatura kaydedildi.');
    }

    /** Telefonla ulaşan müşteri adına iade talebi (süre sınırı yönetici için uygulanmaz). */
    public function storeReturn(Request $request, StoreOrder $storeOrder, ReturnService $returns): RedirectResponse
    {
        try {
            $return = $returns->request($storeOrder, $request->only(['items', 'reason', 'note', 'iban', 'account_name']), true);
        } catch (DomainException $e) {
            return back()->withErrors(['order' => $e->getMessage()])->withInput();
        }

        return redirect()->route('store_admin.returns.show', $return)->with('status', "İade talebi {$return->return_no} müşteri adına açıldı; müşteriye e-posta gönderildi.");
    }

    /** Ürün iadesi olmadan kısmi para iadesi. */
    public function refund(Request $request, StoreOrder $storeOrder): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:20'],
            'method' => ['nullable', 'in:iyzico,manual'],
            'refund_reason' => ['required', 'string', 'max:300'],
        ], [], ['amount' => 'tutar', 'refund_reason' => 'neden']);

        return $this->attempt(fn () => $this->orders->refundWithoutReturn($storeOrder, $data['amount'], $data['method'] ?? 'iyzico', $data['refund_reason'], $request->ip()),
            'Para iadesi yapıldı; müşteriye e-posta gönderildi.');
    }

    public function storeCreditNote(Request $request, StoreOrder $storeOrder, InvoiceService $invoices): RedirectResponse
    {
        return $this->attempt(fn () => $invoices->attachCreditNote($storeOrder, $request->only(['number', 'issued_on', 'amount', 'return_id']), $request->file('file')),
            'İade faturası kaydedildi.');
    }

    public function creditNote(StoreOrder $storeOrder, int $note, InvoiceService $invoices): StreamedResponse
    {
        return $invoices->downloadCreditNote($storeOrder, $note);
    }

    public function invoice(StoreOrder $storeOrder, InvoiceService $invoices): StreamedResponse
    {
        return $invoices->download($storeOrder);
    }

    public function unblock(StoreCodBlock $storeCodBlock): RedirectResponse
    {
        $this->orders->unblockPhone($storeCodBlock);

        return back()->with('status', 'Telefon kapıda ödeme engel listesinden çıkarıldı.');
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
