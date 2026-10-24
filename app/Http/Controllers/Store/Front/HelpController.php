<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Content\HelpPages;
use App\Domain\Store\Payments\BankTransfer;
use App\Domain\Store\Storefront\ProductPage;
use App\Domain\Support\Decimal;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreOrder;
use App\Models\Store\StorePage;
use App\Models\Store\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Yardım sayfaları: içerik Vitrin → Yardım Sayfaları'ndan; tutar ve yöntemler ayarlardan canlı gelir. */
class HelpController extends Controller
{
    public function show(Request $request): View
    {
        $key = $request->route()->defaults['help'];
        $page = HelpPages::get($key);
        abort_unless($page['published'], 404);

        return view('store.front.help.'.$key, ['key' => $key, 'c' => $page['content']] + match ($key) {
            'shipping' => ['info' => ProductPage::info(), 'cargoFee' => Decimal::parse(StoreSetting::get('shipping_cargo_fee'), 2),
                'localFee' => Decimal::parse(StoreSetting::get('local_delivery_fee'), 2)],
            'payment' => ['settings' => StoreSetting::values(), 'banks' => StoreSetting::flag('transfer_enabled') ? BankTransfer::accounts() : []],
            'legal' => ['documents' => StoreLegalDocument::KEYS, 'contactLive' => StorePage::where('key', 'contact')->where('is_published', true)->exists()],
            default => [],
        });
    }

    /** Kargo takip: sipariş numarası ve e-posta eşleşirse sipariş sayfasına (durum ve takip bağlantısı) yönlendirir. */
    public function track(Request $request): RedirectResponse
    {
        abort_unless(HelpPages::isPublished('tracking'), 404);
        $data = $request->validate([
            'order_no' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:190'],
        ], [], ['order_no' => 'sipariş numarası', 'email' => 'e-posta']);

        $order = StoreOrder::where('order_no', strtoupper(trim($data['order_no'])))->first();
        if (! $order || mb_strtolower($order->email) !== mb_strtolower(trim($data['email']))) {
            throw ValidationException::withMessages(['order_no' => 'Bu sipariş numarası ve e-posta ile eşleşen sipariş bulunamadı.']);
        }

        return redirect()->route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
    }
}
