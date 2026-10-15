<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Inventory\Quantity;
use App\Domain\Ordering\ProductRequestService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\ProductRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductRequestController extends Controller
{
    use UsesDealerContext;

    public function __construct(private ProductRequestService $requests) {}

    public function index(): View
    {
        return view('dealer.requests', [
            'requests' => ProductRequest::where('dealer_relationship_id', $this->relationship()->id)
                ->with('product:id,name,sku,unit_id', 'product.unit:id,name')->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], ['quantity' => 'talep edilen miktar', 'note' => 'açıklama']);

        $qty = Quantity::parse($data['quantity']);

        try {
            $qty === null ? throw new DomainException('Geçerli bir miktar girin.') : null;
            $this->requests->create($this->relationship(), (int) $data['product_id'], $qty, $data['note'] ?? null, $request->boolean('notify', true));
        } catch (DomainException $e) {
            return back()->withErrors(['request' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Ürün talebiniz toptancıya iletildi. Talep stok ayırmaz; ürün stoğa girdiğinde Taleplerim sayfasında görürsünüz.');
    }
}
