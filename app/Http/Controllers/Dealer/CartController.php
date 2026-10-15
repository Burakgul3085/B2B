<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Inventory\Quantity;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\CartItem;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CartController extends Controller
{
    use UsesDealerContext;

    public function __construct(private CartService $carts, private OrderService $orders) {}

    public function index(): View
    {
        $relationship = $this->relationship();
        $settings = $this->settings();

        return view('dealer.cart', [
            'summary' => $this->carts->summary($relationship, (int) $settings->default_warehouse_id),
            'addresses' => $relationship->addresses,
            'settings' => $settings,
            'submitToken' => (string) Str::uuid(), // çift gönderim koruması
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'quantity' => ['required', 'string', 'max:20']], [], ['quantity' => 'miktar']);
        $qty = Quantity::parse($data['quantity']);

        if ($qty === null) {
            return back()->withErrors(['cart' => 'Geçerli bir miktar girin.'])->withInput();
        }

        try {
            $item = $this->carts->add($this->relationship(), (int) $data['product_id'], $qty);
        } catch (DomainException $e) {
            return back()->withErrors(['cart' => $e->getMessage()])->withInput();
        }

        return back()->with('status', $item->product->name.' sepete eklendi.');
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->assertOwn($item);
        $qty = Quantity::parse((string) $request->input('quantity'));

        try {
            $qty === null ? throw new DomainException('Geçerli bir miktar girin.') : $this->carts->update($item, $qty);
        } catch (DomainException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return back();
    }

    public function remove(CartItem $item): RedirectResponse
    {
        $this->assertOwn($item);
        $item->delete();

        return back()->with('status', 'Ürün sepetten çıkarıldı.');
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
            'submit_token' => ['required', 'uuid'],
        ], ['address_id.required' => 'Sevk adresi seçin.'], ['note' => 'sipariş notu']);

        try {
            $order = $this->orders->submit($this->relationship(), (int) $data['address_id'], $data['note'] ?? null, $data['submit_token']);
        } catch (DomainException $e) {
            return back()->withErrors(['cart' => $e->getMessage()])->withInput();
        }

        return redirect()->route('dealer.orders.show', $order)
            ->with('status', "Siparişiniz {$order->order_no} numarasıyla toptancıya iletildi. Stok, sipariş onayında kesinleşir.");
    }

    private function assertOwn(CartItem $item): void
    {
        abort_unless($item->cart?->dealer_relationship_id === $this->relationship()->id, 404);
    }
}
