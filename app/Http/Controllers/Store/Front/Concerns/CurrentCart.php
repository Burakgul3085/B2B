<?php

namespace App\Http\Controllers\Store\Front\Concerns;

use App\Domain\Store\Cart\CartService;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * İsteğin sepetini bulur: giriş yapmış müşteride hesabın sepeti, misafirde tarayıcıdaki şifreli çerezde
 * tutulan belirtecin sepeti. Misafir sepeti ilk ekleme anında oluşturulur.
 */
class CurrentCart
{
    public const COOKIE = 'magaza_sepet';

    public function __construct(private CartService $carts) {}

    public function find(Request $request): ?StoreCart
    {
        $customer = $request->user('customer');

        return $customer instanceof StoreCustomer ? $this->carts->forCustomer($customer) : $this->carts->findGuest($request->cookie(self::COOKIE));
    }

    /** Sepeti bulur, yoksa misafir sepeti oluşturur. Çerez ayrıca remember() ile verilir (işlem başarılı olursa). */
    public function findOrCreate(Request $request): StoreCart
    {
        return $this->find($request) ?? $this->carts->createGuest();
    }

    /** Misafir sepetinin belirtecini 30 günlük şifreli çereze yazar. */
    public function remember(StoreCart $cart): void
    {
        if ($cart->customer_id === null) {
            Cookie::queue(Cookie::make(self::COOKIE, $cart->token, 60 * 24 * 30, httpOnly: true, sameSite: 'lax'));
        }
    }

    /** Giriş/üyelik sonrası: misafir sepetini hesaba katar ve çerezi siler. */
    public function mergeInto(Request $request, StoreCustomer $customer): void
    {
        $this->carts->merge($this->carts->findGuest($request->cookie(self::COOKIE)), $customer);
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public function count(Request $request): int
    {
        $customer = $request->user('customer');
        if (! $customer && ! $request->cookie(self::COOKIE)) {
            return 0; // misafir ve sepeti yok: sorgu yapılmaz
        }

        return $this->carts->count($this->find($request));
    }
}
