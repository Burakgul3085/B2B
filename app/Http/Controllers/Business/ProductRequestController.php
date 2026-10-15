<?php

namespace App\Http\Controllers\Business;

use App\Domain\Ordering\ProductRequestService;
use App\Http\Controllers\Controller;
use App\Models\ProductRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductRequestController extends Controller
{
    public function __construct(private ProductRequestService $requests) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...array_keys(ProductRequest::STATUSES), 'open'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'open';

        $requests = ProductRequest::query()
            ->with(['product:id,name,sku,unit_id', 'product.unit:id,name', 'relationship.dealer:id,legal_name'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ProductRequest::OPEN))
            ->when($status !== 'open', fn ($q) => $q->where('status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = addcslashes($term, '%_\\');
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$like}%")->orWhere('sku', 'like', "{$like}%"));
            })
            ->latest('id')->paginate(25)->withQueryString();

        // Aynı ürün için kaç bayinin talep ettiği: tedarik kararı için.
        $demand = ProductRequest::whereIn('status', ProductRequest::OPEN)
            ->selectRaw('product_id, count(*) dealers, sum(quantity) qty')->groupBy('product_id')->get()->keyBy('product_id');

        return view('business.requests', compact('requests', 'status', 'filters', 'demand'));
    }

    public function respond(Request $request, ProductRequest $productRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['in_review', 'available', 'unavailable', 'stock_updated', 'closed'])],
            'response_note' => ['nullable', 'string', 'max:1000'],
        ], [], ['response_note' => 'yanıt']);

        try {
            $this->requests->respond($productRequest, $data['status'], $data['response_note'] ?? null, $request->user()->id);
            app(\App\Domain\Notifications\Notifier::class)->requestUpdated($productRequest->fresh(['product', 'relationship']));
        } catch (DomainException $e) {
            return back()->withErrors(['request' => $e->getMessage()]);
        }

        return back()->with('status', 'Talep güncellendi.');
    }
}
