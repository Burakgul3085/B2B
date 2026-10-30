<?php

namespace App\Domain\Store\Payments;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Notifications\AdminNotifier;
use App\Domain\Store\Orders\OrderMailer;
use App\Domain\Store\Orders\OrderService;
use App\Domain\Support\Money;
use App\Models\Store\StoreOrder;
use App\Models\Store\StorePayment;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Kartla ödeme (iyzico Ödeme Formu, docs/13-magaza-mimarisi.md §9):
 *  1. start():    ödeme formu başlatılır, müşteri iyzico'nun sayfasına yönlenir.
 *  2. complete(): iyzico'nun geri dönüşündeki belirteçle sonuç iyzico'dan SORGULANIR; durum, sepet kimliği (sipariş no),
 *                 tutar, para birimi ve belirteç siparişle eşleşmezse ödeme kabul edilmez. Aynı belirteç ikinci kez işlenmez.
 *                 iyzico yanıt imzası da doğrulanır (ayar: izle / zorunlu).
 *  3. expireStale(): geri dönüşü gelmeyen ödemeler birkaç dakikada bir sorgulanır; form süresi dolanlar kapatılır.
 *
 * Çift / geç ödeme koruması: kapatılmış bir ödeme (süresi dolmuş, yerine yenisi açılmış, başarısız sayılmış) veya zaten
 * ödenmiş / kapanmış bir sipariş için iyzico'dan sonradan başarılı sonuç gelirse para OTOMATİK iade edilir; müşteriye
 * ve yöneticiye e-posta gider. İade alınamazsa ödeme "refund_failed" olarak işaretlenir ve yönetici uyarılır.
 */
class PaymentService
{
    /** iyzico ödeme formunun ömrü (dk); stok da bu kadar ayrılır (CheckoutService::HOLD_MINUTES). */
    public const FORM_MINUTES = 30;

    /** Başlatılmış ödemenin sonucu bu süreden sonra zamanlanmış görevle sorgulanır (dk). */
    public const CHECK_AFTER_MINUTES = 2;

    public function __construct(
        private IyzicoClient $iyzico,
        private OrderService $orders,
        private AdminNotifier $notifier,
        private OrderMailer $mailer,
        private AuditLogger $audit,
    ) {}

    /** @return string iyzico ödeme sayfasının adresi @throws DomainException */
    public function start(StoreOrder $order, string $callbackUrl, ?string $ip): string
    {
        if ($order->status !== 'pending_payment') {
            throw new DomainException('Bu sipariş için ödeme beklenmiyor.');
        }

        try {
            $result = $this->iyzico->initializeCheckoutForm($this->request($order, $callbackUrl, $ip));
        } catch (DomainException $e) {
            $order->payments()->create(['driver' => 'iyzico', 'mode' => $this->iyzico->mode(), 'status' => 'failed', 'amount' => $order->total, 'error_message' => mb_substr($e->getMessage(), 0, 300)]);
            $this->orders->markPaymentFailed($order, 'Ödeme başlatılamadı.');
            throw $e;
        }

        $order->payments()->create([
            'driver' => 'iyzico', 'mode' => $this->iyzico->mode(), 'status' => 'initialized', 'amount' => $order->total, 'token' => $result['token'],
            // Ödeme sayfası adresi: aynı gönderim yinelenirse (geri tuşu, çift tıklama) yeni sipariş yerine bu sayfaya dönülür.
            'raw' => Arr::only($result['raw'], ['status', 'conversationId', 'tokenExpireTime', 'systemTime', 'paymentPageUrl']),
        ]);

        return $result['paymentPageUrl'];
    }

    /** Aynı sipariş için hâlâ geçerli ödeme sayfası (form ömrü dolmadıysa); yoksa null. */
    public function resumeUrl(StoreOrder $order): ?string
    {
        $payment = $order->payments()->where('driver', 'iyzico')->where('status', 'initialized')
            ->where('created_at', '>', now()->subMinutes(self::FORM_MINUTES - 2))->latest('id')->first();

        return $payment?->raw['paymentPageUrl'] ?? null;
    }

    /**
     * Aynı sepetten açılmış ve hâlâ ödeme bekleyen kart siparişleri: önce iyzico'dan sorgulanır; ödenmişse o sipariş
     * döner (yeni ödeme alınmaz). Ödenmemişse ödeme denemeleri kapatılır ve sipariş bırakılır (stok ve indirim geri döner);
     * kapatılan deneme sonradan başarılı olursa otomatik iade edilir.
     */
    public function settlePendingForCart(int $cartId): ?StoreOrder
    {
        foreach (StoreOrder::where('cart_id', $cartId)->where('status', 'pending_payment')->where('payment_method', 'card')->get() as $order) {
            foreach ($order->payments()->where('driver', 'iyzico')->where('status', 'initialized')->pluck('token') as $token) {
                $this->complete($token, finalize: false);
            }
            $order->refresh();
            if ($order->status === 'paid') {
                return $order;
            }
            if ($order->status === 'pending_payment') {
                StorePayment::where('order_id', $order->id)->where('status', 'initialized')
                    ->update(['status' => 'expired', 'error_message' => 'Yeni ödeme denemesi başlatıldı.', 'updated_at' => now()]);
                $this->orders->markPaymentFailed($order, 'Müşteri aynı sepetle yeni bir ödeme denemesi başlattı.');
            }
        }

        return null;
    }

    /**
     * iyzico geri dönüşü / bildirimi / zamanlanmış kontrol / sipariş sayfasındaki canlı kontrol: sonucu iyzico'dan sorgular.
     *
     * @param  bool  $finalize  false: ödeme henüz yapılmamışsa deneme açık bırakılır (müşteri hâlâ ödeme sayfasında olabilir)
     */
    public function complete(string $token, bool $finalize = true): ?StoreOrder
    {
        $payment = StorePayment::with('order')->where('token', $token)->where('driver', 'iyzico')->first();
        if (! $payment) {
            return null;
        }
        $order = $payment->order;
        if (! in_array($payment->status, ['initialized', 'failed', 'expired'], true)) {
            return $order; // daha önce sonuçlandı (başarılı veya otomatik iade edildi)
        }

        try {
            $result = $this->iyzico->retrieveCheckoutForm($token, $order->order_no);
        } catch (DomainException) {
            return $order; // geçici hata: zamanlanmış görev yeniden dener
        }

        $paid = ($result['status'] ?? null) === 'success'
            && ($result['paymentStatus'] ?? null) === 'SUCCESS'
            && ($result['basketId'] ?? null) === $order->order_no
            && isset($result['price']) && bccomp(number_format((float) $result['price'], 2, '.', ''), (string) $payment->amount, 2) === 0
            && (! isset($result['currency']) || $result['currency'] === 'TRY')
            && (! isset($result['token']) || $result['token'] === $token)
            && (int) ($result['fraudStatus'] ?? 1) === 1;

        if ($paid && ! $this->signatureAccepted($result, $token, $order)) {
            return $order; // zorunlu imza modunda imza tutmadı: elle kontrol edilir
        }

        if (! $paid) {
            // Kapatılmış denemeler zaten başarısızdır; açık deneme yalnızca kesin sonuçta (geri dönüş / süre sonu) kapatılır.
            if ($payment->status === 'initialized' && $finalize) {
                $closed = StorePayment::whereKey($payment->id)->where('status', 'initialized')->update($this->resultFields($result, 'failed'));
                if ($closed === 1) {
                    return $this->orders->markPaymentFailed($order, 'Ödeme alınamadı: '.($result['errorMessage'] ?? $result['paymentStatus'] ?? 'bilinmeyen hata'));
                }
            }

            return $order->fresh();
        }

        // Başarılı ödeme: bu deneme kimin? Aynı belirteç aynı anda iki kez gelirse yalnızca biri sonuçlandırır.
        $previous = $payment->status;
        $claimed = StorePayment::whereKey($payment->id)->where('status', $previous)->update($this->resultFields($result, 'succeeded'));
        if ($claimed !== 1) {
            return $order->fresh();
        }

        $current = StoreOrder::find($order->id);
        if ($previous === 'initialized' && $current->status === 'pending_payment') {
            return $this->orders->markPaid($order);
        }

        // Kapatılmış deneme geç ödendi ya da sipariş başka bir ödemeyle ödendi / kapandı: fazla ödeme iade edilir.
        $this->refundOrphan($payment->fresh(), $current);

        return $current->fresh();
    }

    /** Sipariş sayfasındaki canlı kontrol: ödeme bekleyen siparişin açık denemesini (en fazla 10 sn'de bir) sorgular. */
    public function poll(StoreOrder $order): StoreOrder
    {
        if ($order->status !== 'pending_payment') {
            return $order;
        }
        $payment = $order->payments()->where('driver', 'iyzico')->where('status', 'initialized')
            ->where('created_at', '<', now()->subSeconds(10))->latest('id')->first();
        if ($payment && Cache::add("store-pay-poll:{$payment->id}", 1, 10)) {
            $this->complete($payment->token, finalize: false);
        }

        return $order->fresh();
    }

    /**
     * Zamanlanmış görev: CHECK_AFTER_MINUTES dakikadan eski açık denemeler sorgulanır (ödendiyse sipariş hemen sonuçlanır);
     * form süresi ($minutes) dolan, hâlâ ödeme bekleyen siparişler kapatılır (stok serbest kalır).
     *
     * @return int sonuçlandırılan / kapatılan sipariş sayısı
     */
    public function expireStale(int $minutes = self::FORM_MINUTES + 5): int
    {
        StorePayment::where('driver', 'iyzico')->where('status', 'initialized')
            ->where('created_at', '<', now()->subMinutes(self::CHECK_AFTER_MINUTES))->where('created_at', '>', now()->subDay())
            ->pluck('token')->each(fn ($token) => $this->complete($token, finalize: false));

        $count = 0;
        StoreOrder::where('status', 'pending_payment')->where('created_at', '<', now()->subMinutes($minutes))
            ->with('payments')->each(function (StoreOrder $order) use (&$count) {
                foreach ($order->payments->where('status', 'initialized') as $pending) {
                    $this->complete($pending->token);
                }
                if ($order->fresh()->status === 'pending_payment') {
                    StorePayment::where('order_id', $order->id)->where('status', 'initialized')
                        ->update(['status' => 'expired', 'error_message' => 'Süre doldu.', 'updated_at' => now()]);
                    $this->orders->markPaymentFailed($order, 'Ödeme süresi doldu.');
                }
                $count++;
            });

        return $count;
    }

    /**
     * Fazla / geç ödemenin otomatik iadesi: ödemenin her sepet kalemi kendi işlem kimliğiyle tamamen iade edilir.
     * Sipariş tutarları değişmez (bu ödeme siparişin ödemesi sayılmaz).
     */
    private function refundOrphan(StorePayment $payment, StoreOrder $order): void
    {
        $error = null;
        $transactions = $payment->item_transactions ?? [];
        if (! $transactions) {
            $error = 'iyzico işlem bilgisi yok';
        }
        foreach ($transactions as $i => $t) {
            $left = bcsub($t['paid_price'], $t['refunded'] ?? '0.00', 2);
            if (bccomp($left, '0', 2) <= 0) {
                continue;
            }
            try {
                $response = $this->iyzico->refund($t['transaction_id'], $left, $order->order_no, null);
                if (($response['status'] ?? null) === 'success') {
                    $transactions[$i]['refunded'] = bcadd($t['refunded'] ?? '0.00', $left, 2);
                } else {
                    $error ??= (string) ($response['errorMessage'] ?? 'iyzico iadeyi kabul etmedi');
                }
            } catch (Throwable $e) {
                $error ??= $e->getMessage();
            }
        }

        $ok = $error === null;
        $payment->forceFill([
            'item_transactions' => $transactions,
            'status' => $ok ? 'auto_refunded' : 'refund_failed',
            'error_message' => mb_substr($ok ? 'Fazla / geç ödeme otomatik iade edildi.' : 'Fazla / geç ödeme iade EDİLEMEDİ: '.$error.' — iyzico panelinden iade edin.', 0, 300),
        ])->save();

        $amount = Money::format($payment->amount);
        $this->orders->event($order, 'payment_duplicate', $ok
            ? "Fazla / geç gelen {$amount} kart ödemesi otomatik iade edildi."
            : "DİKKAT: Fazla / geç gelen {$amount} kart ödemesi otomatik iade edilemedi; iyzico panelinden iade edin.");
        $this->audit->log($ok ? 'store.payment_auto_refunded' : 'store.payment_auto_refund_failed', $order, [],
            ['order_no' => $order->order_no, 'payment_id' => $payment->id, 'amount' => (string) $payment->amount, 'error' => $error]);
        $this->notifier->duplicatePayment($order, $payment, $ok);
        $this->mailer->duplicatePayment($order, (string) $payment->amount, $ok);
    }

    /** İmza tutmazsa yönetici uyarılır; "zorunlu" modda ödeme kabul edilmez. */
    private function signatureAccepted(array $result, string $token, StoreOrder $order): bool
    {
        $valid = $this->iyzico->checkoutSignatureValid($result, $token);
        if ($valid === true) {
            return true;
        }
        $enforce = StoreSetting::get('iyzico_signature') === 'enforce';
        if (Cache::add("store-pay-signature:{$token}", 1, 3600)) {
            $this->audit->log('store.payment_signature_mismatch', $order, [], ['order_no' => $order->order_no, 'signature' => $valid === null ? 'yok' : 'tutmadı', 'enforced' => $enforce]);
            $this->notifier->signatureMismatch($order, $valid === null, $enforce);
        }

        return ! $enforce;
    }

    /** iyzico sonucundan ödeme kaydına yazılan alanlar (kart numarası değil, yalnızca aile ve son dört hane). */
    private function resultFields(array $result, string $status): array
    {
        $paid = $status === 'succeeded';

        return [
            'status' => $status,
            'provider_payment_id' => isset($result['paymentId']) ? (string) $result['paymentId'] : null,
            'installment' => $result['installment'] ?? null,
            'card_family' => isset($result['cardFamily']) ? mb_substr((string) $result['cardFamily'], 0, 40) : null,
            'card_last_four' => isset($result['lastFourDigits']) ? substr((string) $result['lastFourDigits'], -4) : null,
            'error_code' => isset($result['errorCode']) ? mb_substr((string) $result['errorCode'], 0, 40) : null,
            'error_message' => isset($result['errorMessage']) ? mb_substr((string) $result['errorMessage'], 0, 300) : null,
            'raw' => json_encode(Arr::only($result, ['status', 'paymentStatus', 'paymentId', 'price', 'paidPrice', 'currency', 'basketId', 'installment',
                'fraudStatus', 'cardType', 'cardAssociation', 'cardFamily', 'binNumber', 'lastFourDigits', 'errorCode', 'errorMessage', 'mdStatus'])),
            // İade, kalem bazındaki işlem kimlikleri üzerinden yapılır (RefundService).
            'item_transactions' => $paid ? json_encode(collect($result['itemTransactions'] ?? [])->map(fn ($t) => [
                'item_id' => (string) ($t['itemId'] ?? ''),
                'transaction_id' => (string) ($t['paymentTransactionId'] ?? ''),
                'paid_price' => number_format((float) ($t['paidPrice'] ?? $t['price'] ?? 0), 2, '.', ''),
                'refunded' => '0.00',
            ])->filter(fn ($t) => $t['transaction_id'] !== '')->values()->all()) : null,
            'updated_at' => now(),
        ];
    }

    private function request(StoreOrder $order, string $callbackUrl, ?string $ip): array
    {
        [$first, $last] = array_pad(explode(' ', $order->shipping_name, 2), 2, '-');
        $address = $order->shippingOneLine();
        // Kalem tutarı indirim payı düşülmüş tutardır; kalemlerin toplamı sipariş tutarına eşit olmalıdır.
        $items = $order->items()->get()->map(fn ($i) => [
            'id' => (string) $i->id,
            'name' => mb_substr($i->product_name.($i->option_label ? " ({$i->option_label})" : ''), 0, 200),
            'category1' => $i->print_job_id ? 'Fotokopi / Baskı' : 'Kırtasiye',
            'itemType' => 'PHYSICAL',
            'price' => bcsub((string) $i->line_total, (string) $i->discount_amount, 2),
        ])->filter(fn ($i) => bccomp($i['price'], '0', 2) > 0)->values()->all();
        $fees = bcadd((string) $order->shipping_fee, (string) $order->cod_fee, 2);
        if (bccomp($fees, '0', 2) > 0) {
            $items[] = ['id' => 'teslimat', 'name' => 'Teslimat ücreti', 'category1' => 'Hizmet', 'itemType' => 'VIRTUAL', 'price' => $fees];
        }

        return [
            'locale' => 'tr',
            'conversationId' => $order->order_no,
            'price' => number_format((float) $order->total, 2, '.', ''),
            'paidPrice' => number_format((float) $order->total, 2, '.', ''),
            'currency' => 'TRY',
            'basketId' => $order->order_no,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => $callbackUrl,
            'enabledInstallments' => [1, 2, 3, 6, 9],
            'buyer' => [
                'id' => $order->customer_id ? 'M'.$order->customer_id : 'G'.$order->id,
                'name' => $first,
                'surname' => $last,
                'gsmNumber' => '+90'.substr($order->phone, 1),
                'email' => $order->email,
                // TCKN girilmediyse iyzico'nun kabul ettiği varsayılan değer kullanılır.
                'identityNumber' => $order->billing_type === 'individual' && $order->billing_tax_number ? $order->billing_tax_number : '11111111111',
                'registrationAddress' => $address,
                'ip' => $ip ?? $order->ip ?? '127.0.0.1',
                'city' => $order->shipping_province,
                'country' => 'Turkey',
            ],
            'shippingAddress' => ['contactName' => $order->shipping_name, 'city' => $order->shipping_province, 'country' => 'Turkey', 'address' => $address],
            'billingAddress' => ['contactName' => $order->billing_name, 'city' => $order->shipping_province, 'country' => 'Turkey', 'address' => $order->billing_address],
            'basketItems' => $items,
        ];
    }
}
