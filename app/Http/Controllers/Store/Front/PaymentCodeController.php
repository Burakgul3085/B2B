<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\PaymentCodes\PaymentCodeService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Özel ödeme kodunu sepete ekler. Müşteri yalnızca kodu gönderir; tutar sunucuda kodun kaydından gelir.
 * Kod denemesi (kaba kuvvet) rota üzerinde sınırlıdır.
 */
class PaymentCodeController extends Controller
{
    public function __construct(private PaymentCodeService $codes, private CurrentCart $current) {}

    public function add(Request $request): RedirectResponse
    {
        $input = (string) $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => 'ödeme kodu'])['code'];

        try {
            // Ekleme başarısızsa yeni oluşturulan misafir sepeti de geri alınır.
            $cart = DB::transaction(function () use ($request, $input) {
                $cart = $this->current->findOrCreate($request);
                $this->codes->addToCart($cart, $input);

                return $cart;
            });
        } catch (DomainException $e) {
            return back()->withErrors(['payment_code' => $e->getMessage()])->withInput();
        }
        $this->current->remember($cart);

        return redirect()->route('store.cart')->with('status', 'Ödeme kodu sepetinize eklendi.');
    }
}
