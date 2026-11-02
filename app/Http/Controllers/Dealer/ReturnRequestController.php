<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Notifications\Notifier;
use App\Domain\Ordering\ReturnRequestService;
use App\Http\Controllers\Concerns\ServesReturnPhotos;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderReturnRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Bayinin iade talepleri: tamamlanmış siparişten talep açma, geri çekme, fotoğraf görüntüleme. */
class ReturnRequestController extends Controller
{
    use ServesReturnPhotos, UsesDealerContext;

    public function __construct(private ReturnRequestService $requests, private Notifier $notifier) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        $relationshipId = $this->relationship()->id;
        abort_unless($order->dealer_relationship_id === $relationshipId, 404);

        $data = $request->validate([
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'string', 'max:20'],
            'reason' => ['required', Rule::in(array_keys(OrderReturnRequest::REASONS))],
            'note' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.OrderReturnRequest::MAX_PHOTOS],
            'photos.*' => ['file', 'max:5120'],
        ], ['photos.max' => 'En fazla '.OrderReturnRequest::MAX_PHOTOS.' fotoğraf ekleyebilirsiniz.', 'photos.*.max' => 'Her fotoğraf en fazla 5 MB olabilir.'],
            ['reason' => 'iade nedeni', 'note' => 'açıklama']);

        try {
            $returnRequest = $this->requests->request($order, $relationshipId, $data['qty'], $data['reason'], $data['note'] ?? null,
                $request->file('photos', []), $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['return' => $e->getMessage()])->withInput();
        }

        $this->notifier->returnRequested($returnRequest);

        return back()->with('status', "İade talebiniz ({$returnRequest->request_no}) toptancınıza iletildi. Onaylandığında ürünleri nasıl göndereceğiniz bildirilecek.");
    }

    public function cancel(OrderReturnRequest $returnRequest): RedirectResponse
    {
        abort_unless($returnRequest->dealer_relationship_id === $this->relationship()->id, 404);

        try {
            $returnRequest = $this->requests->cancelByDealer($returnRequest, $this->relationship()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        $this->notifier->returnWithdrawn($returnRequest);

        return back()->with('status', 'İade talebiniz geri çekildi.');
    }

    public function photo(OrderReturnRequest $returnRequest, Attachment $attachment): StreamedResponse
    {
        abort_unless($returnRequest->dealer_relationship_id === $this->relationship()->id, 404);

        return $this->streamPhoto($returnRequest, $attachment);
    }
}
