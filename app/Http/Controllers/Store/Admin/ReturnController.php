<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Returns\ReturnService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreReturn;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** İade talepleri: liste, ayrıntı; onay, ret, ürünün teslim alınması (stoğa dönüş) ve para iadesi. */
class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(array_keys(StoreReturn::STATUSES))], 'q' => ['nullable', 'string', 'max:40']]);

        return view('store.admin.returns.index', [
            'returns' => StoreReturn::with('order')->withSum('items', 'quantity')
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('return_no', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_no', 'like', "%{$term}%")->orWhere('shipping_name', 'like', "%{$term}%"))))
                ->latest('id')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'counts' => StoreReturn::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->all(),
        ]);
    }

    public function show(StoreReturn $storeReturn): View
    {
        $storeReturn->load(['order', 'items.orderItem', 'refunds']);

        return view('store.admin.returns.show', [
            'return' => $storeReturn,
            'maxRefund' => $storeReturn->status === 'received' ? $this->returns->maxRefund($storeReturn) : null,
        ]);
    }

    public function approve(Request $request, StoreReturn $storeReturn): RedirectResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        return $this->attempt(fn () => $this->returns->approve($storeReturn, $note), 'İade onaylandı; müşteriye gönderim bilgisi e-postayla iletildi.');
    }

    public function reject(Request $request, StoreReturn $storeReturn): RedirectResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'gerekçe'])['reason'];

        return $this->attempt(fn () => $this->returns->reject($storeReturn, $reason), 'İade talebi reddedildi; müşteriye bildirildi.');
    }

    public function receive(Request $request, StoreReturn $storeReturn): RedirectResponse
    {
        $data = $request->validate(['resellable' => ['nullable', 'array'], 'resellable.*' => ['integer', 'min:0']]);

        return $this->attempt(fn () => $this->returns->receive($storeReturn, array_map('intval', $data['resellable'] ?? [])),
            'Ürün teslim alındı; sağlam ürünler stoğa döndü.');
    }

    public function refund(Request $request, StoreReturn $storeReturn): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'string', 'max:20'], 'note' => ['nullable', 'string', 'max:300']], [], ['amount' => 'iade tutarı', 'note' => 'açıklama']);

        return $this->attempt(fn () => $this->returns->refund($storeReturn, $data['amount'], $data['note'] ?? null, $request->ip()),
            'Para iadesi yapıldı; müşteriye e-posta gönderildi.');
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['return' => $e->getMessage()])->withInput();
        }

        return back()->with('status', $message);
    }
}
