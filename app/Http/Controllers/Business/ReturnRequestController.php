<?php

namespace App\Http\Controllers\Business;

use App\Domain\Notifications\Notifier;
use App\Domain\Ordering\ReturnRequestService;
use App\Domain\Support\Money;
use App\Http\Controllers\Concerns\ServesReturnPhotos;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\OrderReturnRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Bayi iade talepleri: liste, inceleme, onay / ret, ürünleri teslim alma. */
class ReturnRequestController extends Controller
{
    use ServesReturnPhotos;

    public function __construct(private ReturnRequestService $requests, private Notifier $notifier) {}

    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in([...array_keys(OrderReturnRequest::STATUSES), 'open'])]])['status'] ?? 'open';

        return view('business.returns.index', [
            'status' => $status,
            'requests' => OrderReturnRequest::with(['order:id,order_no', 'relationship.dealer:id,legal_name'])->withCount('items')
                ->when($status === 'open', fn ($q) => $q->whereIn('status', OrderReturnRequest::OPEN), fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString(),
            'counts' => OrderReturnRequest::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(OrderReturnRequest $returnRequest): View
    {
        $returnRequest->load(['order', 'relationship.dealer', 'items.orderItem', 'photos', 'orderReturn.items']);

        return view('business.returns.show', ['r' => $returnRequest]);
    }

    public function approve(Request $request, OrderReturnRequest $returnRequest): RedirectResponse
    {
        $note = $request->validate(['business_note' => ['nullable', 'string', 'max:1000']], [], ['business_note' => 'bayiye not'])['business_note'] ?? null;

        return $this->run(fn () => $this->requests->approve($returnRequest, $note, $request->user()->id), 'İade talebi onaylandı; bayiye bildirildi.');
    }

    public function reject(Request $request, OrderReturnRequest $returnRequest): RedirectResponse
    {
        $reason = $request->validate(['reject_reason' => ['required', 'string', 'max:1000']], [], ['reject_reason' => 'ret gerekçesi'])['reject_reason'];

        return $this->run(fn () => $this->requests->reject($returnRequest, $reason, $request->user()->id), 'İade talebi reddedildi; bayiye bildirildi.');
    }

    public function receive(Request $request, OrderReturnRequest $returnRequest): RedirectResponse
    {
        $data = $request->validate([
            'received' => ['required', 'array'], 'received.*' => ['nullable', 'string', 'max:20'],
            'resellable' => ['required', 'array'], 'resellable.*' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['note' => 'not']);

        $result = null;
        $response = $this->run(function () use ($returnRequest, $data, $request, &$result) {
            return $result = $this->requests->receive($returnRequest, $data['received'], $data['resellable'], $data['note'] ?? null, $request->user()->id);
        }, '');

        return $result
            ? $response->with('status', 'Ürünler teslim alındı: bayinin carisine '.Money::format($result->orderReturn->grand_total).' alacak yazıldı, tekrar satılabilir miktar depoya girdi.')
            : $response;
    }

    public function photo(OrderReturnRequest $returnRequest, Attachment $attachment): StreamedResponse
    {
        return $this->streamPhoto($returnRequest, $attachment);
    }

    /** İşlemi çalıştırır; başarılıysa (commit sonrası) bayiye bildirim ve e-posta gönderir. */
    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $returnRequest = $action();
        } catch (DomainException $e) {
            return back()->withErrors(['return' => $e->getMessage()])->withInput();
        }

        $this->notifier->returnStatusChanged($returnRequest);

        return back()->with('status', $message);
    }
}
