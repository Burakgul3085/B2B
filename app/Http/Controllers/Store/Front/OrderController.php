<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Orders\InvoiceService;
use App\Domain\Store\Orders\OrderService;
use App\Domain\Store\Returns\ReturnService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreReturn;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sipariş görüntüleme: üye kendi siparişini hesabından, misafir (ve e-postadaki bağlantı) sipariş numarası +
 * gizli belirteçle görür. Belirteç sabit zamanlı karşılaştırılır; başka siparişe erişim 404'tür.
 * Kargoya verilmemiş siparişi müşteri buradan iptal eder (OrderService::cancelByCustomer).
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('store.front.account.orders', [
            'orders' => StoreOrder::where('customer_id', $request->user('customer')->id)->withCount('items')->latest('id')->paginate(10),
        ]);
    }

    public function showOwn(Request $request, string $orderNo, ReturnService $returns): View
    {
        return $this->render($this->own($request, $orderNo), $returns, route('store.orders.return', $orderNo), route('store.orders.invoice', $orderNo),
            fn (int $note) => route('store.orders.credit-note', ['orderNo' => $orderNo, 'note' => $note]));
    }

    public function showByToken(string $orderNo, string $token, ReturnService $returns): View
    {
        $params = ['orderNo' => $orderNo, 'token' => $token];

        return $this->render($this->byToken($orderNo, $token), $returns, route('store.order.return', $params), route('store.order.invoice', $params),
            fn (int $note) => route('store.order.credit-note', $params + ['note' => $note]));
    }

    public function cancelOwn(Request $request, string $orderNo, OrderService $orders): RedirectResponse
    {
        return $this->cancel($request, $this->own($request, $orderNo), $orders);
    }

    public function cancelByToken(Request $request, string $orderNo, string $token, OrderService $orders): RedirectResponse
    {
        return $this->cancel($request, $this->byToken($orderNo, $token), $orders);
    }

    public function invoiceOwn(Request $request, string $orderNo, InvoiceService $invoices): StreamedResponse
    {
        return $invoices->download($this->own($request, $orderNo));
    }

    public function invoiceByToken(string $orderNo, string $token, InvoiceService $invoices): StreamedResponse
    {
        return $invoices->download($this->byToken($orderNo, $token));
    }

    public function creditNoteOwn(Request $request, string $orderNo, int $note, InvoiceService $invoices): StreamedResponse
    {
        return $invoices->downloadCreditNote($this->own($request, $orderNo), $note);
    }

    public function creditNoteByToken(string $orderNo, string $token, int $note, InvoiceService $invoices): StreamedResponse
    {
        return $invoices->downloadCreditNote($this->byToken($orderNo, $token), $note);
    }

    private function cancel(Request $request, StoreOrder $order, OrderService $orders): RedirectResponse
    {
        try {
            $cancelled = $orders->cancelByCustomer($order, $request->only(['reason', 'note', 'iban', 'account_name']), $request->ip());
        } catch (DomainException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()])->withInput();
        }

        return back()->with('status', $cancelled
            ? 'Siparişiniz iptal edildi.'.($order->fresh()->payment_status === 'refunded' ? ' Ödemeniz kartınıza iade edildi; hesabınıza yansıması bankanıza göre birkaç gün sürebilir.' : '')
            : 'İptal talebiniz alındı. Ödemenizi bildirdiğiniz hesaba gönderip siparişinizi iptal edeceğiz.');
    }

    private function own(Request $request, string $orderNo): StoreOrder
    {
        return StoreOrder::where('order_no', $orderNo)->where('customer_id', $request->user('customer')->id)->firstOrFail();
    }

    private function byToken(string $orderNo, string $token): StoreOrder
    {
        $order = StoreOrder::where('order_no', $orderNo)->firstOrFail();
        abort_unless(hash_equals($order->access_token, $token), 404);

        return $order;
    }

    private function render(StoreOrder $order, ReturnService $returns, string $returnUrl, string $invoiceUrl, \Closure $creditNoteUrl): View
    {
        $order->load(['items', 'events', 'consents', 'returns.items', 'returns.photos', 'invoice', 'creditNotes']);
        $refusal = $returns->refusal($order);

        return view('store.front.order', [
            'order' => $order,
            'returnUrl' => $returnUrl,
            'invoiceUrl' => $invoiceUrl,
            'creditNoteUrl' => $creditNoteUrl,
            'canReturn' => $refusal === null,
            'returnable' => $refusal === null ? $returns->returnableQuantities($order) : [],
            'returnDeadline' => ReturnService::deadline($order),
            'cancelUrl' => $order->customer_id && auth('customer')->id() === $order->customer_id
                ? route('store.orders.cancel', $order->order_no) : route('store.order.cancel', ['orderNo' => $order->order_no, 'token' => $order->access_token]),
            'shipUrl' => fn (StoreReturn $r) => $order->customer_id && auth('customer')->id() === $order->customer_id
                ? route('store.orders.return.ship', [$order->order_no, $r->return_no])
                : route('store.order.return.ship', ['orderNo' => $order->order_no, 'token' => $order->access_token, 'returnNo' => $r->return_no]),
        ]);
    }
}
