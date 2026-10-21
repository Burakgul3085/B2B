<?php

namespace App\Http\Controllers\Store\Front;

use App\Http\Controllers\Controller;
use App\Models\Store\StoreOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sipariş görüntüleme: üye kendi siparişini hesabından, misafir (ve e-postadaki bağlantı) sipariş numarası +
 * gizli belirteçle görür. Belirteç sabit zamanlı karşılaştırılır; başka siparişe erişim 404'tür.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('store.front.account.orders', [
            'orders' => StoreOrder::where('customer_id', $request->user('customer')->id)->withCount('items')->latest('id')->paginate(10),
        ]);
    }

    public function showOwn(Request $request, string $orderNo): View
    {
        $order = StoreOrder::where('order_no', $orderNo)->where('customer_id', $request->user('customer')->id)->firstOrFail();

        return $this->render($order);
    }

    public function showByToken(string $orderNo, string $token): View
    {
        $order = StoreOrder::where('order_no', $orderNo)->firstOrFail();
        abort_unless(hash_equals($order->access_token, $token), 404);

        return $this->render($order);
    }

    private function render(StoreOrder $order): View
    {
        return view('store.front.order', ['order' => $order->load(['items', 'events', 'consents'])]);
    }
}
