<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Returns\ReturnService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreReturn;
use App\Models\Store\StoreReturnPhoto;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Müşterinin iade talebi (fotoğraflarıyla) ve onaylanan iadenin kargo bilgisi: üye kendi siparişinden, misafir sipariş
 * bağlantısından (numara + gizli belirteç). Fotoğraflar yalnızca imzalı, süreli bağlantıyla gösterilir.
 */
class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns) {}

    public function storeOwn(Request $request, string $orderNo): RedirectResponse
    {
        return $this->create($request, $this->own($request, $orderNo));
    }

    public function storeByToken(Request $request, string $orderNo, string $token): RedirectResponse
    {
        return $this->create($request, $this->byToken($orderNo, $token));
    }

    public function shipOwn(Request $request, string $orderNo, string $returnNo): RedirectResponse
    {
        return $this->ship($request, $this->own($request, $orderNo), $returnNo);
    }

    public function shipByToken(Request $request, string $orderNo, string $token, string $returnNo): RedirectResponse
    {
        return $this->ship($request, $this->byToken($orderNo, $token), $returnNo);
    }

    public function photo(int $photo): Response
    {
        $photo = StoreReturnPhoto::findOrFail($photo);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, null, ['Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function create(Request $request, StoreOrder $order): RedirectResponse
    {
        try {
            $return = $this->returns->request($order, $request->only(['items', 'reason', 'note', 'iban', 'account_name']) + ['photos' => $request->file('photos', [])]);
        } catch (DomainException $e) {
            return back()->withErrors(['return' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "İade talebiniz alındı ({$return->return_no}). Size e-postayla bilgi vereceğiz.");
    }

    private function ship(Request $request, StoreOrder $order, string $returnNo): RedirectResponse
    {
        $return = StoreReturn::where('order_id', $order->id)->where('return_no', $returnNo)->firstOrFail();
        try {
            $this->returns->ship($return, $request->only(['cargo_company', 'cargo_tracking', 'cargo_fee']));
        } catch (DomainException $e) {
            return back()->withErrors(['return' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Kargo bilginizi aldık ({$return->return_no}). Ürünler elimize ulaşınca iadenizi yapacağız.");
    }

    private function own(Request $request, string $orderNo): StoreOrder
    {
        return StoreOrder::where('order_no', $orderNo)->where('customer_id', $request->user('customer')->id)->firstOrFail();
    }

    private function byToken(string $orderNo, string $token): StoreOrder
    {
        $order = StoreOrder::where('order_no', $orderNo)->firstOrFail();
        abort_unless(hash_equals($order->access_token, $token), 404);

        return $order;
    }
}
