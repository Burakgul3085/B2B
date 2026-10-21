<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Discounts\DiscountService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Sepet: görüntüleme, ekleme, adet değiştirme, çıkarma, kupon. Fiyat, stok ve indirim her gösterimde sunucuda hesaplanır. */
class CartController extends Controller
{
    public function __construct(private CartService $carts, private CurrentCart $current) {}

    public function show(Request $request, DiscountService $discounts): View
    {
        $cart = $this->current->find($request);
        $summary = $this->carts->summary($cart);
        $customer = $request->user('customer');
        ['applied' => $discount, 'couponError' => $couponError] = $discounts->evaluate($summary, $cart?->coupon_code, $customer?->email, $customer?->phone);

        return view('store.front.cart', ['summary' => $summary, 'cart' => $cart, 'discount' => $discount, 'couponError' => $couponError]);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:40']], [], ['code' => 'kupon kodu'])['code'];
        $cart = $this->current->find($request);
        if (! $cart) {
            return back()->withErrors(['code' => 'Kupon eklemek için önce sepetinize ürün ekleyin.']);
        }

        try {
            $this->carts->setCoupon($cart, $code);
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Kupon uygulandı.');
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        if ($cart = $this->current->find($request)) {
            $this->carts->setCoupon($cart, null);
        }

        return back()->with('status', 'Kupon kaldırıldı.');
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ], [], ['quantity' => 'adet']);

        try {
            // Ekleme başarısızsa yeni oluşturulan misafir sepeti de geri alınır.
            $cart = DB::transaction(function () use ($request, $data) {
                $cart = $this->current->findOrCreate($request);
                $this->carts->add($cart, (int) $data['variant_id'], (int) ($data['quantity'] ?? 1));

                return $cart;
            });
        } catch (DomainException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }
        $this->current->remember($cart);

        return back()->with('cart_added', true)->with('status', 'Ürün sepetinize eklendi.');
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $quantity = (int) $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_QUANTITY]], [], ['quantity' => 'adet'])['quantity'];
        $cart = $this->current->find($request) ?? abort(404);

        try {
            $this->carts->update($cart, $item, $quantity);
        } catch (DomainException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return back();
    }

    public function remove(Request $request, int $item): RedirectResponse
    {
        $this->carts->remove($this->current->find($request) ?? abort(404), $item);

        return back()->with('status', 'Ürün sepetten çıkarıldı.');
    }
}
