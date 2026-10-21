<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Returns\ReturnService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreOrder;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Müşterinin iade talebi: üye kendi siparişinden, misafir sipariş bağlantısından (numara + gizli belirteç). */
class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns) {}

    public function storeOwn(Request $request, string $orderNo): RedirectResponse
    {
        $order = StoreOrder::where('order_no', $orderNo)->where('customer_id', $request->user('customer')->id)->firstOrFail();

        return $this->create($request, $order);
    }

    public function storeByToken(Request $request, string $orderNo, string $token): RedirectResponse
    {
        $order = StoreOrder::where('order_no', $orderNo)->firstOrFail();
        abort_unless(hash_equals($order->access_token, $token), 404);

        return $this->create($request, $order);
    }

    private function create(Request $request, StoreOrder $order): RedirectResponse
    {
        try {
            $return = $this->returns->request($order, $request->only(['items', 'reason', 'note', 'iban', 'account_name']));
        } catch (DomainException $e) {
            return back()->withErrors(['return' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "İade talebiniz alındı ({$return->return_no}). Size e-postayla bilgi vereceğiz.");
    }
}
