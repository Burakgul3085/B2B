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
use App\Domain\Store\Print\PrintSettings;
use App\Domain\Store\Sms\OtpService;
use App\Domain\Support\Decimal;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\Province;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Ödeme akışı:
 *  - Kart: sipariş oluşur (stok ayrılır) → iyzico ödeme sayfası → geri dönüşte sonuç iyzico'dan sorgulanır.
 *  - Kapıda ödeme: taslak oturuma alınır → telefona SMS kodu → kod doğrulanınca sepet ve tutar yeniden denetlenir
 *    ve sipariş oluşur.
 *  - Havale / EFT: sipariş hemen oluşur; sipariş sayfasında hesap bilgileri ve son ödeme zamanı gösterilir.
 *
 * Çift gönderim koruması: form her açılışta tek kullanımlık bir anahtar (submit_token) taşır; aynı anahtarla gelen ikinci
 * istek (çift tıklama, geri tuşu, yanıtı kaybolan istek) yeni sipariş açmaz, ilk siparişe (kartta ödeme sayfasına) döner.
 * Kartta aynı sepetin bekleyen siparişi önce iyzico'dan sorgulanır; ödenmişse yeni ödeme alınmaz.
 */
class CheckoutController extends Controller
{
    private const DRAFT_KEY = 'checkout.cod_draft';

    /** Aynı IP veya e-postadan aynı anda en fazla bu kadar ödenmemiş kart siparişi (stok kilitlenmesine karşı). */
    private const MAX_PENDING_CARD = 3;

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
        if ($notice = app(PrintSettings::class)->minimumNotice($summary)) {
            return redirect()->route('store.cart')->withErrors(['cart' => $notice]);
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
            'cargoCutoff' => StoreSetting::get('cargo_cutoff'),
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
            'submitToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, PaymentService $payments, OtpService $otp): RedirectResponse
    {
        $customer = $request->user('customer');
        $cart = $this->current->find($request) ?? abort(404);

        $token = $request->input('submit_token');
        $token = is_string($token) && Str::isUuid($token) ? strtolower($token) : (string) Str::uuid();
        if ($existing = StoreOrder::where('submit_token', $token)->first()) {
            return $this->resume($existing, $payments); // aynı gönderim ikinci kez geldi
        }

        try {
            $draft = $this->checkout->validate($request->all(), $customer, $this->carts->summary($cart), $cart->coupon_code);
        } catch (DomainException $e) {
            return redirect()->route('store.cart')->withErrors(['cart' => $e->getMessage()]);
        }
        $draft['submit_token'] = $token;

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
            } catch (UniqueConstraintViolationException) {
                return $this->resume(StoreOrder::where('submit_token', $token)->firstOrFail(), $payments);
            } catch (InsufficientStoreStock) {
                return redirect()->route('store.cart')->withErrors(['cart' => 'Bazı ürünlerin stoğu az önce değişti. Sepetinizi kontrol edin.']);
            } catch (DomainException $e) {
                return redirect()->route('store.cart')->withErrors(['cart' => $e->getMessage()]);
            }

            return redirect()->route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token])->with('order_placed', true);
        }

        // Aynı sepetin bekleyen kart siparişi: ödendiyse ona dönülür; ödenmediyse kapatılır (stok bırakılır).
        if ($paid = $payments->settlePendingForCart($cart->id)) {
            return $this->orderRedirect($paid)->with('status', 'Bu sepet için ödemeniz zaten alınmış; yeni ödeme yapılmadı.');
        }
        $pending = StoreOrder::where('status', 'pending_payment')->where('payment_method', 'card')
            ->where('created_at', '>', now()->subMinutes(PaymentService::FORM_MINUTES + 5))
            ->where(fn ($q) => $q->where('ip', $request->ip())->orWhere('email', $draft['email']))->count();
        if ($pending >= self::MAX_PENDING_CARD) {
            return back()->withErrors(['payment_method' => 'Tamamlanmamış ödemeleriniz var. Birkaç dakika sonra tekrar deneyin veya açık siparişinizin ödemesini tamamlayın.'])->withInput();
        }

        try {
            $order = $this->checkout->place($draft, $customer, $cart, $request->ip(), $request->userAgent());
            $url = $payments->start($order, $this->callbackUrl(), $request->ip());
        } catch (UniqueConstraintViolationException) {
            return $this->resume(StoreOrder::where('submit_token', $token)->firstOrFail(), $payments);
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
        $lines = [...array_map(fn ($l) => [$l->variant->id, $l->quantity(), $l->unitPrice()], $summary->orderable()),
            ...array_map(fn ($l) => [null, 1, $l->total()], $summary->printsOrderable())];
        $expected = array_map(fn ($l) => [$l['variant_id'], $l['quantity'], $l['unit_price']], $draft['lines']);

        // Kod beklenirken sepet, fiyat veya kapıda ödeme uygunluğu değiştiyse sipariş oluşturulmaz.
        if (! $cart || $summary->hasIssues() || $lines !== $expected
            || $cod->refusal($draft['delivery_method'], $draft['shipping']['province_id'], $draft['shipping']['district_id'], $draft['total'], $draft['phone'],
                CodPolicy::addressKey($draft['shipping']['district_id'], $draft['shipping']['neighborhood'], $draft['shipping']['address']))) {
            $request->session()->forget(self::DRAFT_KEY);

            return redirect()->route('store.cart')->withErrors(['cart' => 'Sepetiniz veya sipariş koşulları değişti. Lütfen kontrol edip yeniden onaylayın.']);
        }

        if (! empty($draft['submit_token']) && ($existing = StoreOrder::where('submit_token', $draft['submit_token'])->first())) {
            $request->session()->forget(self::DRAFT_KEY);

            return $this->orderRedirect($existing); // aynı doğrulama ikinci kez gönderildi
        }

        try {
            $order = $this->checkout->place($draft, $customer, $cart, $request->ip(), $request->userAgent());
        } catch (UniqueConstraintViolationException) {
            $request->session()->forget(self::DRAFT_KEY);

            return $this->orderRedirect(StoreOrder::where('submit_token', $draft['submit_token'])->firstOrFail());
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

    /**
     * iyzico geri dönüşü (başka siteden POST; CSRF ve oturum çerezi gelmez, rota oturumsuzdur). Sonuç iyzico'dan
     * sorgulanır; sonuç mesajı sipariş sayfasında siparişin gerçek durumuna göre gösterilir (?odeme=sonuc).
     */
    public function callback(Request $request, PaymentService $payments): RedirectResponse
    {
        $token = $request->input('token');
        $order = is_string($token) && $token !== '' && strlen($token) <= 120 ? $payments->complete($token) : null;
        if (! $order) {
            return redirect()->route('store.home');
        }

        return redirect()->route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token, 'odeme' => 'sonuc']);
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

    /** Aynı gönderim yinelendi: kartta hâlâ geçerli ödeme sayfasına, aksi hâlde sipariş sayfasına. */
    private function resume(StoreOrder $order, PaymentService $payments): RedirectResponse
    {
        if ($order->status === 'pending_payment' && ($url = $payments->resumeUrl($order))) {
            return redirect()->away($url);
        }

        return $this->orderRedirect($order);
    }

    private function orderRedirect(StoreOrder $order): RedirectResponse
    {
        return redirect()->route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]);
    }

    /** iyzico dönüş adresi canlıda sabit mağaza alan adından üretilir (gelen isteğin Host başlığından değil). */
    private function callbackUrl(): string
    {
        return app()->isProduction()
            ? 'https://'.config('store.domain').route('store.payment.callback', absolute: false)
            : route('store.payment.callback');
    }

    private function draft(Request $request): ?array
    {
        $data = $request->session()->get(self::DRAFT_KEY);

        return $data && $data['expires'] > now()->timestamp ? $data['draft'] : null;
    }
}
