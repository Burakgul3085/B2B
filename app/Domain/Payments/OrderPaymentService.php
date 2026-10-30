<?php

namespace App\Domain\Payments;

use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\LedgerService;
use App\Domain\Notifications\Notifier;
use App\Domain\Support\LocalTime;
use App\Domain\Tenancy\TenantContext;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Models\Order;
use App\Models\OrderPayment;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * B2B sipariş ödemesi. Bayi, toptancı onayladıktan sonra ONAYLANAN tutarı öder (kısmi onayda iade gerekmez):
 *  - Kart: iyzico Ödeme Formu. Sonuç tarayıcıdan değil iyzico'dan sorgulanır; durum, sepet kimliği (sipariş no) ve
 *    tutar siparişle eşleşmezse ödeme kabul edilmez. Aynı belirteç ikinci kez işlenmez.
 *  - Havale / teslimde ödeme: toptancı parayı alınca "Ödeme alındı" ile kaydeder.
 * Başarılı her ödeme bayinin carisine tahsilat yazar; sipariş iptalinde kart ödemesi iyzico'dan iade edilir ve
 * tahsilat ters kayıtla iptal edilir. Havale / nakit / POS ödemesi carisinde alacak olarak kalır (iade toptancıdadır).
 */
class OrderPaymentService
{
    public function __construct(
        private PaymentSettings $settings,
        private LedgerService $ledger,
        private AuditLogger $audit,
        private TenantContext $tenant,
        private Notifier $notifier,
    ) {}

    /** @return string iyzico ödeme sayfasının adresi @throws DomainException */
    public function startCard(Order $order, string $callbackUrl, ?string $ip): string
    {
        $this->assertPayable($order);
        if (! $this->settings->cardAvailable()) {
            throw new DomainException('Kartla ödeme şu anda kullanılamıyor. Havale ile ödeyebilir veya toptancınızla iletişime geçebilirsiniz.');
        }
        if ($order->payment_method !== 'card') {
            $order->update(['payment_method' => 'card']);
        }

        $payment = OrderPayment::create(['order_id' => $order->id, 'driver' => 'iyzico', 'status' => 'initialized', 'amount' => $order->grand_total]);
        try {
            $result = $this->settings->iyzico()->initializeCheckoutForm($this->request($order, $callbackUrl, $ip));
        } catch (DomainException $e) {
            $payment->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 300)]);
            throw $e;
        }
        $payment->update(['token' => $result['token'], 'raw' => Arr::only($result['raw'], ['status', 'conversationId', 'tokenExpireTime', 'systemTime'])]);

        return $result['paymentPageUrl'];
    }

    /**
     * iyzico geri dönüşü veya zamanlanmış kontrol: belirtecin işletmesi bulunur, sonuç o işletmenin bağlamında
     * iyzico'dan sorgulanır. İşletme bağlamı tarayıcıdan değil, belirtecin kayıtlı ödemesinden kurulur.
     */
    public function completeToken(string $token): ?Order
    {
        $businessId = OrderPayment::withoutBusinessScope()->where('token', $token)->where('driver', 'iyzico')->value('business_id');

        return $businessId ? $this->tenant->runAs($businessId, fn () => $this->complete($token)) : null;
    }

    /** Aktif işletme bağlamında kart ödemesini sonuçlandırır. */
    public function complete(string $token): ?Order
    {
        $payment = OrderPayment::with('order')->where('token', $token)->where('driver', 'iyzico')->first();
        if (! $payment) {
            return null;
        }
        $order = $payment->order;
        if ($payment->status !== 'initialized') {
            return $order;
        }

        try {
            $result = $this->settings->iyzico()->retrieveCheckoutForm($token, $order->order_no);
        } catch (DomainException) {
            return $order; // geçici hata: zamanlanmış görev yeniden dener
        }

        $paid = ($result['status'] ?? null) === 'success'
            && ($result['paymentStatus'] ?? null) === 'SUCCESS'
            && ($result['basketId'] ?? null) === $order->order_no
            && isset($result['price']) && bccomp(number_format((float) $result['price'], 2, '.', ''), (string) $payment->amount, 2) === 0
            && (int) ($result['fraudStatus'] ?? 1) === 1;

        $newlyPaid = false;
        $order = DB::transaction(function () use ($payment, $result, $paid, &$newlyPaid) {
            // Aynı belirteç aynı anda iki kez gelirse yalnızca biri sonuçlandırır.
            $updated = OrderPayment::whereKey($payment->id)->where('status', 'initialized')->update([
                'status' => $paid ? 'succeeded' : 'failed',
                'provider_payment_id' => isset($result['paymentId']) ? (string) $result['paymentId'] : null,
                'transaction_id' => $paid ? (string) ($result['itemTransactions'][0]['paymentTransactionId'] ?? '') ?: null : null,
                'installment' => $result['installment'] ?? null,
                'card_family' => isset($result['cardFamily']) ? mb_substr((string) $result['cardFamily'], 0, 40) : null,
                'card_last_four' => isset($result['lastFourDigits']) ? substr((string) $result['lastFourDigits'], -4) : null,
                'error_code' => isset($result['errorCode']) ? mb_substr((string) $result['errorCode'], 0, 40) : null,
                'error_message' => isset($result['errorMessage']) ? mb_substr((string) $result['errorMessage'], 0, 300) : null,
                'raw' => json_encode(Arr::only($result, ['status', 'paymentStatus', 'paymentId', 'price', 'paidPrice', 'basketId', 'installment',
                    'fraudStatus', 'cardType', 'cardAssociation', 'cardFamily', 'binNumber', 'lastFourDigits', 'errorCode', 'errorMessage', 'mdStatus'])),
                'updated_at' => now(),
            ]);
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            if ($updated !== 1 || ! $paid) {
                return $order;
            }
            if (! $order->awaitingPayment()) {
                // Sipariş bu arada başka yolla ödendi veya iptal edildi: tahsilat yazılmaz, iade için toptancı uyarılır.
                OrderPayment::whereKey($payment->id)->update(['error_message' => 'Sipariş bu ödeme gelmeden önce ödenmiş veya iptal edilmişti; iade gerekebilir.']);
                $this->audit->log('order.payment_unexpected', $order, [], ['amount' => (string) $payment->amount]);

                return $order;
            }

            $newlyPaid = true;

            return $this->markPaid($order, $payment->fresh(), 'card_online', 'Kartla ödeme (iyzico)', null);
        });
        if ($newlyPaid) {
            $this->notifier->orderPaid($order->fresh('relationship.dealer'), 'Kredi / banka kartı');
        }

        return $order;
    }

    /**
     * Toptancı havale / nakit / POS ödemesini kaydeder (havale hesaba geçince veya teslimde ödeme alınınca).
     *
     * @throws DomainException
     */
    public function recordManual(Order $order, string $method, string $date, ?string $note, int $userId): Order
    {
        if (! isset(OrderPayment::MANUAL_METHODS[$method])) {
            throw new DomainException('Geçerli bir ödeme yöntemi seçin.');
        }

        return DB::transaction(function () use ($order, $method, $date, $note, $userId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->awaitingPayment() || ! in_array($order->status, Order::ACTIVE_FULFILMENT, true)) {
                throw new DomainException('Bu sipariş için ödeme beklenmiyor.');
            }
            $payment = OrderPayment::create([
                'order_id' => $order->id, 'driver' => 'manual', 'status' => 'succeeded', 'amount' => $order->grand_total,
                'method' => $method, 'created_by' => $userId, 'raw' => $note ? ['note' => mb_substr($note, 0, 300)] : null,
            ]);

            return $this->markPaid($order, $payment, $method, OrderPayment::MANUAL_METHODS[$method].($note ? ': '.$note : ''), $userId, $date);
        });
    }

    /**
     * Bayi, onaylanmış ve ödenmemiş siparişin ödeme yöntemini değiştirir (ör. karttan havaleye). Açık hesaba geçerse
     * ödeme beklenmez (yalnızca izinli bayiler).
     *
     * @throws DomainException
     */
    public function changeMethod(Order $order, string $method): Order
    {
        return DB::transaction(function () use ($order, $method) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($order);
            if (! isset($this->settings->methodsFor($order->relationship)[$method])) {
                throw new DomainException('Bu ödeme yöntemi kullanılamıyor.');
            }
            $old = $order->payment_method;
            $order->update(['payment_method' => $method, 'payment_status' => $method === 'open_account' ? null : 'awaiting']);
            $this->audit->log('order.payment_method_changed', $order, ['payment_method' => $old], ['payment_method' => $method]);

            return $order;
        });
    }

    /**
     * Sipariş iptalinde (kilitli sipariş, açık transaction içinde): kart ödemesi iyzico'dan iade edilir ve tahsilat
     * ters kayıtla iptal edilir. İade alınamazsa istisna atılır, iptal geri alınır.
     *
     * @return string|null bayiye geri ödenmesi gereken (iade edilmeyen) tutar; yoksa null
     *
     * @throws DomainException
     */
    public function settleCancellation(Order $order, string $reason, ?string $ip = null): ?string
    {
        $manual = '0.00';
        $refunded = false;
        foreach (OrderPayment::where('order_id', $order->id)->where('status', 'succeeded')->get() as $payment) {
            if ($payment->driver !== 'iyzico') {
                $manual = bcadd($manual, (string) $payment->amount, 2);

                continue;
            }
            if (! $payment->transaction_id) {
                throw new DomainException('Kart ödemesinin işlem kimliği bulunamadı; iadeyi iyzico panelinden yapıp tahsilatı elle iptal edin.');
            }
            $result = $this->settings->iyzico()->refund($payment->transaction_id, bcadd((string) $payment->amount, '0', 2), $order->order_no, $ip);
            if (($result['status'] ?? null) !== 'success') {
                throw new DomainException('Kart iadesi yapılamadı: '.($result['errorMessage'] ?? 'bilinmeyen hata').'. Sipariş iptal edilmedi.');
            }
            if ($payment->collection_id && ($collection = CollectionEntry::find($payment->collection_id)) && ! $collection->isCancelled()) {
                $this->ledger->cancelCollection($collection, 'Sipariş iptali, kart iadesi: '.$reason);
            }
            $payment->update(['status' => 'refunded', 'refunded_at' => now()]);
            $refunded = true;
        }

        $order->payment_status = $refunded ? 'refunded' : ($order->payment_status === 'paid' ? 'paid' : null);
        if ($refunded) {
            $this->audit->log('order.payment_refunded', $order, [], ['reason' => $reason]);
        }

        return bccomp($manual, '0', 2) > 0 ? $manual : null;
    }

    /** Geri dönüşü gelmemiş eski kart ödemelerini sonuçlandırır (tüm işletmeler). @return int işlenen ödeme sayısı */
    public function expireStale(int $minutes = 30): int
    {
        $count = 0;
        OrderPayment::withoutBusinessScope()->where('driver', 'iyzico')->where('status', 'initialized')
            ->where('created_at', '<', now()->subMinutes($minutes))->get()
            ->each(function (OrderPayment $payment) use (&$count) {
                $this->tenant->runAs($payment->business_id, function () use ($payment) {
                    if ($payment->token) {
                        $this->complete($payment->token);
                    }
                    OrderPayment::whereKey($payment->id)->where('status', 'initialized')->update(['status' => 'failed', 'error_message' => 'Süre doldu.', 'updated_at' => now()]);
                });
                $count++;
            });

        return $count;
    }

    private function markPaid(Order $order, OrderPayment $payment, string $collectionMethod, string $description, ?int $userId, ?string $date = null): Order
    {
        $account = CurrentAccount::where('dealer_relationship_id', $order->dealer_relationship_id)->firstOrFail();
        $collection = $this->ledger->recordCollection($account, [
            'collected_on' => $date ?? LocalTime::today(),
            'amount' => (string) $payment->amount,
            'method' => $collectionMethod,
            'order_id' => $order->id,
            'description' => "Sipariş {$order->order_no} — {$description}",
        ], $userId);
        $payment->update(['collection_id' => $collection->id]);
        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
        $this->audit->log('order.paid', $order, [], ['amount' => (string) $payment->amount, 'driver' => $payment->driver, 'method' => $collectionMethod]);

        return $order;
    }

    /** @throws DomainException */
    private function assertPayable(Order $order): void
    {
        if (! $order->awaitingPayment() || ! in_array($order->status, ['approved', 'preparing'], true)) {
            throw new DomainException('Bu sipariş için ödeme beklenmiyor.');
        }
    }

    private function request(Order $order, string $callbackUrl, ?string $ip): array
    {
        $order->loadMissing('relationship.dealer.user');
        $dealer = $order->relationship->dealer;
        [$first, $last] = array_pad(explode(' ', trim($order->ship_recipient ?: $dealer->legal_name), 2), 2, '-');
        $digits = preg_replace('/\D/', '', (string) ($dealer->phone ?: $order->ship_phone));
        $address = mb_substr($order->ship_address.' '.$order->ship_district.' / '.$order->ship_city, 0, 250);
        $price = number_format((float) $order->grand_total, 2, '.', '');

        return [
            'locale' => 'tr',
            'conversationId' => $order->order_no,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => 'TRY',
            'basketId' => $order->order_no,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => $callbackUrl,
            'enabledInstallments' => $this->settings->installments(),
            'buyer' => array_filter([
                'id' => 'B'.$order->dealer_relationship_id,
                'name' => $first,
                'surname' => $last,
                'gsmNumber' => strlen($digits) >= 10 ? '+90'.substr($digits, -10) : null,
                'email' => $dealer->user?->email ?? $dealer->email,
                // 11 haneli vergi / TC kimlik no yoksa iyzico'nun kabul ettiği varsayılan değer kullanılır.
                'identityNumber' => preg_match('/^\d{11}$/', (string) $dealer->tax_number) ? $dealer->tax_number : '11111111111',
                'registrationAddress' => $address,
                'ip' => $ip ?? '127.0.0.1',
                'city' => $order->ship_city,
                'country' => 'Turkey',
            ], fn ($v) => $v !== null),
            'shippingAddress' => ['contactName' => $order->ship_recipient, 'city' => $order->ship_city, 'country' => 'Turkey', 'address' => $address],
            'billingAddress' => ['contactName' => $dealer->legal_name, 'city' => $order->ship_city, 'country' => 'Turkey', 'address' => $address],
            // Tek kalem: onaylanan sipariş tutarının tamamı (iade bu kalemin işlem kimliğiyle yapılır).
            'basketItems' => [['id' => 'siparis', 'name' => mb_substr("Sipariş {$order->order_no}", 0, 200), 'category1' => 'Kırtasiye', 'itemType' => 'PHYSICAL', 'price' => $price]],
        ];
    }
}
