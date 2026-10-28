<?php

namespace App\Domain\Store\Payments;

use App\Domain\Store\Orders\OrderService;
use App\Models\Store\StoreOrder;
use App\Models\Store\StorePayment;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Kartla ödeme (iyzico Ödeme Formu, docs/13-magaza-mimarisi.md §9):
 *  1. start():    ödeme formu başlatılır, müşteri iyzico'nun sayfasına yönlenir.
 *  2. complete(): iyzico'nun geri dönüşündeki belirteçle sonuç iyzico'dan SORGULANIR; durum, sepet kimliği (sipariş no)
 *                 ve ödenen tutar siparişle eşleşmezse ödeme kabul edilmez. Aynı belirteç ikinci kez işlenmez.
 *  3. expire():   geri dönüşü gelmeyen eski ödemeler zamanlanmış görevle sorgulanır ve sonuçlandırılır.
 */
class PaymentService
{
    public function __construct(private IyzicoClient $iyzico, private OrderService $orders) {}

    /** @return string iyzico ödeme sayfasının adresi @throws DomainException */
    public function start(StoreOrder $order, string $callbackUrl, ?string $ip): string
    {
        if ($order->status !== 'pending_payment') {
            throw new DomainException('Bu sipariş için ödeme beklenmiyor.');
        }

        try {
            $result = $this->iyzico->initializeCheckoutForm($this->request($order, $callbackUrl, $ip));
        } catch (DomainException $e) {
            $order->payments()->create(['driver' => 'iyzico', 'status' => 'failed', 'amount' => $order->total, 'error_message' => mb_substr($e->getMessage(), 0, 300)]);
            $this->orders->markPaymentFailed($order, 'Ödeme başlatılamadı.');
            throw $e;
        }

        $order->payments()->create([
            'driver' => 'iyzico', 'status' => 'initialized', 'amount' => $order->total, 'token' => $result['token'],
            'raw' => Arr::only($result['raw'], ['status', 'conversationId', 'tokenExpireTime', 'systemTime']),
        ]);

        return $result['paymentPageUrl'];
    }

    /** iyzico geri dönüşü veya zamanlanmış kontrol: sonucu sorgular ve siparişi sonuçlandırır. */
    public function complete(string $token): ?StoreOrder
    {
        $payment = StorePayment::with('order')->where('token', $token)->where('driver', 'iyzico')->first();
        if (! $payment) {
            return null;
        }
        if ($payment->status !== 'initialized') {
            return $payment->order; // daha önce sonuçlandı
        }

        $order = $payment->order;
        try {
            $result = $this->iyzico->retrieveCheckoutForm($token, $order->order_no);
        } catch (DomainException) {
            return $order; // geçici hata: zamanlanmış görev yeniden dener
        }

        $paid = ($result['status'] ?? null) === 'success'
            && ($result['paymentStatus'] ?? null) === 'SUCCESS'
            && ($result['basketId'] ?? null) === $order->order_no
            && isset($result['price']) && bccomp(number_format((float) $result['price'], 2, '.', ''), (string) $order->total, 2) === 0
            && (int) ($result['fraudStatus'] ?? 1) === 1;

        $won = DB::transaction(function () use ($payment, $result, $paid) {
            // Aynı belirteç aynı anda iki kez gelirse yalnızca biri sonuçlandırır.
            $updated = StorePayment::whereKey($payment->id)->where('status', 'initialized')->update([
                'status' => $paid ? 'succeeded' : 'failed',
                'provider_payment_id' => isset($result['paymentId']) ? (string) $result['paymentId'] : null,
                'installment' => $result['installment'] ?? null,
                'card_family' => isset($result['cardFamily']) ? mb_substr((string) $result['cardFamily'], 0, 40) : null,
                'card_last_four' => isset($result['lastFourDigits']) ? substr((string) $result['lastFourDigits'], -4) : null,
                'error_code' => isset($result['errorCode']) ? mb_substr((string) $result['errorCode'], 0, 40) : null,
                'error_message' => isset($result['errorMessage']) ? mb_substr((string) $result['errorMessage'], 0, 300) : null,
                'raw' => json_encode(Arr::only($result, ['status', 'paymentStatus', 'paymentId', 'price', 'paidPrice', 'basketId', 'installment',
                    'fraudStatus', 'cardType', 'cardAssociation', 'cardFamily', 'binNumber', 'lastFourDigits', 'errorCode', 'errorMessage', 'mdStatus'])),
                // İade, kalem bazındaki işlem kimlikleri üzerinden yapılır (RefundService).
                'item_transactions' => $paid ? json_encode(collect($result['itemTransactions'] ?? [])->map(fn ($t) => [
                    'item_id' => (string) ($t['itemId'] ?? ''),
                    'transaction_id' => (string) ($t['paymentTransactionId'] ?? ''),
                    'paid_price' => number_format((float) ($t['paidPrice'] ?? $t['price'] ?? 0), 2, '.', ''),
                    'refunded' => '0.00',
                ])->filter(fn ($t) => $t['transaction_id'] !== '')->values()->all()) : null,
                'updated_at' => now(),
            ]);

            return $updated === 1;
        });

        if (! $won) {
            return $order->fresh();
        }

        return $paid
            ? $this->orders->markPaid($order)
            : $this->orders->markPaymentFailed($order, 'Ödeme alınamadı: '.($result['errorMessage'] ?? $result['paymentStatus'] ?? 'bilinmeyen hata'));
    }

    /** Geri dönüşü gelmemiş, eski "ödeme bekleniyor" siparişlerini sonuçlandırır. @return int işlenen sipariş sayısı */
    public function expireStale(int $minutes = 30): int
    {
        $count = 0;
        StoreOrder::where('status', 'pending_payment')->where('created_at', '<', now()->subMinutes($minutes))
            ->with('payments')->each(function (StoreOrder $order) use (&$count) {
                $pending = $order->payments->firstWhere('status', 'initialized');
                $order = $pending ? ($this->complete($pending->token) ?? $order) : $order;
                if ($order->fresh()->status === 'pending_payment') {
                    $pending?->update(['status' => 'failed', 'error_message' => 'Süre doldu.']);
                    $this->orders->markPaymentFailed($order, 'Ödeme süresi doldu.');
                }
                $count++;
            });

        return $count;
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
