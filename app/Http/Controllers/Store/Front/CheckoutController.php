<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Customers\PhoneNumber;
use App\Domain\Store\Discounts\DiscountService;
use App\Domain\Store\Inventory\InsufficientStoreStock;
use App\Domain\Store\Orders\CheckoutService;
use App\Domain\Store\Orders\CodPolicy;
use App\Domain\Store\Orders\DeliveryRules;
use App\Domain\Store\Orders\LegalDocuments;
use App\Domain\Store\Payments\BankTransfer;
use App\Domain\Store\Payments\PaymentService;
use App\Domain\Store\Sms\OtpService;
use App\Domain\Support\Decimal;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\Province;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ödeme akışı:
 *  - Kart: sipariş oluşur (stok ayrılır) → iyzico ödeme sayfası → geri dönüşte sonuç iyzico'dan sorgulanır.
 *  - Kapıda ödeme: taslak oturuma alınır → telefona SMS kodu → kod doğrulanınca sepet ve tutar yeniden denetlenir
 *    ve sipariş oluşur.
 *  - Havale / EFT: sipariş hemen oluşur; sipariş sayfasında hesap bilgileri ve son ödeme zamanı gösterilir.
 */
class CheckoutController extends Controller
{
    private const DRAFT_KEY = 'checkout.cod_draft';

    public function __construct(private CheckoutService $checkout, private CartService $carts, private CurrentCart $current) {}

    public function show(Request $request, DeliveryRules $delivery, CodPolicy $cod, DiscountService $discounts): View|RedirectResponse
    {
        $cart = $this->current->find($request);
        $summary = $this->carts->summary($cart);
        if ($summary->isEmpty()) {
            return redirect()->route('store.cart');
        }
        if ($summary->hasIssues()) {
            return redirect()->route('store.cart')->withErrors(['cart' => 'Sepetinizdeki bazı ürünlerin stok durumu değişti; devam etmeden önce düzenleyin.']);
        }

        $customer = $request->user('customer');
        $threshold = Decimal::parse(StoreSetting::get('shipping_free_threshold'), 2);
        // Önizleme: kişiye bağlı kupon koşulları (ilk sipariş, kişi başı kullanım) sipariş onayında denetlenir.
        ['applied' => $discount, 'couponError' => $couponError] = $discounts->evaluate($summary, $cart?->coupon_code, $customer?->email, $customer?->phone, $customer);
        $net = bcsub($summary->subtotal(), $discount?->amount ?? '0.00', 2);

        return view('store.front.checkout', [
            'summary' => $summary,
            'discount' => $discount,
            'couponError' => $couponError,
            'customer' => $customer,
            'addresses' => $customer ? $customer->addresses()->with(['province', 'district'])->get() : collect(),
            'provinces' => Province::orderBy('name')->pluck('name', 'id'),
            // Ekrandaki tutar önizlemesi için kurallar; sipariş tutarı sunucuda yeniden hesaplanır.
            'rules' => [
                'subtotal' => $net,
                'cargoFee' => $discount?->freeShipping ? '0.00' : $delivery->cargoFee($net),
                // Ücretsiz kargo sınırı aşıldıysa firma seçilmez; aşılmadıysa müşteri firmayı fiyatıyla seçer.
                'cargoFree' => $delivery->cargoIsFree($net),
                'shippingWaived' => (bool) $discount?->freeShipping,
                'carriers' => $delivery->carrierChoices()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'fee' => (string) $c->fee, 'logo' => $c->logoUrl()])->values()->all(),
                'freeThreshold' => $threshold,
                'localEnabled' => StoreSetting::flag('local_delivery_enabled'),
                'localFee' => $discount?->freeShipping ? '0.00' : (Decimal::parse(StoreSetting::get('local_delivery_fee'), 2) ?? '0.00'),
                'localProvince' => Province::ESKISEHIR,
                'localDistricts' => $delivery->localDistrictIds(),
                'codEnabled' => StoreSetting::flag('cod_enabled'),
                'codFee' => $cod->fee(),
                'codMax' => Decimal::parse(StoreSetting::get('cod_max'), 2),
                'cardAvailable' => $this->checkout->cardAvailable(),
                'transferAvailable' => BankTransfer::available(),
            ],
            'legal' => collect(StoreLegalDocument::CHECKOUT_KEYS)->mapWithKeys(fn ($key) => [$key => StoreLegalDocument::current($key)]),
        ]);
    }

    public function store(Request $request, PaymentService $payments, OtpService $otp): RedirectResponse
    {
        $customer = $request->user('customer');
        $cart = $this->current->find($request) ?? abort(404);

        try {
            $draft = $this->checkout->validate($request->all(), $customer, $this->carts->summary($cart), $cart->coupon_code);
        } catch (DomainException $e) {
            return redirect()->route('store.cart')->withErrors(['cart' => $e->getMessage()]);
        }

        if ($draft['payment_method'] === 'cod') {
            try {
                $otp->send($draft['phone'], 'cod', $request->ip());
            } catch (DomainException $e) {
                return back()->withErrors(['payment_method' => $e->getMessage()])->withInput();
            }
            $request->session()->put(self::DRAFT_KEY, ['draft' => $draft, 'expires' => now()->addMinutes(15)->timestamp]);

            return redirect()->route('store.checkout.verify');
        }

        if ($draft['payment_method'] === 'transfer') {
            try {
                $order = $this->checkout->place($draft, $customer, $cart, $request->ip(), $request->userAgent());
            } catch (InsufficientStoreStock) {
                return redirect()->route('store.cart')->withErrors(['cart' => 'Bazı ürünlerin stoğu az önce değişti. Sepetinizi kontrol edin.']);
            } catch (DomainException $e) {
                return redirect()->route('store.cart')->withErrors(['cart' => $e->getMessage()]);
            }

            return redirect()->route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token])->with('order_placed', true);
        }

        try {
            $order = $this->checkout->place($draft, $customer, $cart, $request->ip(), $request->userAgent());
            $url = $payments->start($order, route('store.payment.callback'), $request->ip());
        } catch (InsufficientStoreStock) {
            return redirect()->route('store.cart')->withErrors(['cart' => 'Bazı ürünlerin stoğu az önce değişti. Sepetinizi kontrol edin.']);
        } catch (DomainException $e) {
            return back()->withErrors(['payment_method' => $e->getMessage()])->withInput();
        }

        return redirect()->away($url);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (! $draft = $this->draft($request)) {
            return redirect()->route('store.checkout');
        }

        return view('store.front.checkout-verify', [
            'phone' => PhoneNumber::format($draft['phone']),
            'total' => $draft['total'],
            // Geliştirmede SMS gönderilmez; kod uygulama günlüğüne yazılır.
            'smsLogged' => StoreSetting::get('sms_driver') !== 'netgsm',
        ]);
    }

    public function verify(Request $request, OtpService $otp, CodPolicy $cod): RedirectResponse
    {
        $draft = $this->draft($request);
        if (! $draft) {
            return redirect()->route('store.checkout')->withErrors(['payment_method' => 'Doğrulama süresi doldu. Lütfen siparişi yeniden onaylayın.']);
        }
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:10']], [], ['code' => 'doğrulama kodu'])['code'];

        try {
            $otp->verify($draft['phone'], 'cod', $code);
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $customer = $request->user('customer');
        $cart = $this->current->find($request);
        $summary = $this->carts->summary($cart);
        $lines = array_map(fn ($l) => [$l->variant->id, $l->quantity(), $l->unitPrice()], $summary->orderable());
        $expected = array_map(fn ($l) => [$l['variant_id'], $l['quantity'], $l['unit_price']], $draft['lines']);

        // Kod beklenirken sepet, fiyat veya kapıda ödeme uygunluğu değiştiyse sipariş oluşturulmaz.
        if (! $cart || $summary->hasIssues() || $lines !== $expected
            || $cod->refusal($draft['delivery_method'], $draft['shipping']['province_id'], $draft['shipping']['district_id'], $draft['total'], $draft['phone'],
                CodPolicy::addressKey($draft['shipping']['district_id'], $draft['shipping']['neighborhood'], $draft['shipping']['address']))) {
            $request->session()->forget(self::DRAFT_KEY);

            return redirect()->route('store.cart')->withErrors(['cart' => 'Sepetiniz veya sipariş koşulları değişti. Lütfen kontrol edip yeniden onaylayın.']);
        }

        try {
            $order = $this->checkout->place($draft, $customer, $cart, $request->ip(), $request->userAgent());
        } catch (InsufficientStoreStock) {
            return redirect()->route('store.cart')->withErrors(['cart' => 'Bazı ürünlerin stoğu az önce değişti. Sepetinizi kontrol edin.']);
        } catch (DomainException $e) { // ör. kupon kullanım sınırı bu arada doldu
            $request->session()->forget(self::DRAFT_KEY);

            return redirect()->route('store.cart')->withErrors(['cart' => $e->getMessage()]);
        }
        $request->session()->forget(self::DRAFT_KEY);

        return redirect()->route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token])->with('order_placed', true);
    }

    public function resend(Request $request, OtpService $otp): RedirectResponse
    {
        $draft = $this->draft($request) ?? abort(404);
        try {
            $otp->send($draft['phone'], 'cod', $request->ip());
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('status', 'Yeni doğrulama kodu gönderildi.');
    }

    /**
     * iyzico anlık bildirimi (webhook; iyzico panelinde bildirim adresi olarak girilir). İçeriğe güvenilmez: yalnızca
     * ödeme belirteci alınır ve sonuç geri dönüşteki gibi iyzico'dan sorgulanır; aynı ödeme ikinci kez işlenmez.
     * Bilinmeyen belirteçte de 200 döner (iyzico'nun gereksiz tekrarlarını ve belirteç yoklamasını önler).
     */
    public function webhook(Request $request, PaymentService $payments): JsonResponse
    {
        $token = $request->input('token');
        if (is_string($token) && $token !== '' && strlen($token) <= 120) {
            $payments->complete($token);
        }

        return response()->json(['status' => 'ok']);
    }

    /** iyzico geri dönüşü (başka siteden POST; CSRF dışında). Sonuç iyzico'dan sorgulanır. */
    public function callback(Request $request, PaymentService $payments): RedirectResponse
    {
        $token = (string) $request->input('token');
        $order = $token !== '' ? $payments->complete($token) : null;
        if (! $order) {
            return redirect()->route('store.home')->withErrors(['cart' => 'Ödeme bilgisi bulunamadı.']);
        }

        $route = redirect()->route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]);

        return $order->status === 'payment_failed'
            ? $route->withErrors(['cart' => 'Ödeme alınamadı. Kart bilgilerinizi kontrol edip yeniden sipariş verebilirsiniz.'])
            : $route->with('order_placed', $order->status !== 'pending_payment');
    }

    /** Kısa yol: ön bilgilendirme ve sözleşme şablonları (sipariş bilgileri onay anında doldurulur). */
    public function legal(string $key, LegalDocuments $legal): View
    {
        abort_unless(array_key_exists($key, StoreLegalDocument::KEYS), 404);
        $doc = StoreLegalDocument::current($key);

        return view('store.front.legal', ['document' => $doc, 'html' => LegalDocuments::html($legal->fill($doc, [
            'name' => '(Alıcı adı)', 'address' => '(Teslimat adresi)', 'phone' => '(Telefon)', 'email' => '(E-posta)', 'lines' => [],
            'shipping_fee' => '0', 'cod_fee' => '0', 'total' => '0', 'payment' => '(Ödeme yöntemi)', 'delivery' => '(Teslimat yöntemi)',
        ]))]);
    }

    private function draft(Request $request): ?array
    {
        $data = $request->session()->get(self::DRAFT_KEY);

        return $data && $data['expires'] > now()->timestamp ? $data['draft'] : null;
    }
}
