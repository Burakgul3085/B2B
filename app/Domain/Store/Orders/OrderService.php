<?php

namespace App\Domain\Store\Orders;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Notifications\AdminNotifier;
use App\Domain\Store\Payments\BankTransfer;
use App\Domain\Store\Payments\RefundService;
use App\Domain\Support\Money;
use App\Domain\Support\Payments\Iban;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreCartItem;
use App\Models\Store\StoreCodBlock;
use App\Models\Store\StoreDiscountRedemption;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreStockMovement;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Sipariş durum geçişleri (docs/13-magaza-mimarisi.md §8.3). Her geçiş:
 *  - siparişi kilitler, izin verilen önceki durumu denetler (aynı işlem iki kez uygulanmaz),
 *  - stok etkisini uygular (ayırmayı satışa çevirme/bırakma, iptalde iade hareketi),
 *  - durum geçmişine ve işlem geçmişine yazar, müşteriye e-posta gönderir.
 */
class OrderService
{
    public function __construct(
        private StoreStockService $stock,
        private AuditLogger $audit,
        private CurrentActor $actor,
        private OrderMailer $mailer,
        private RefundService $refunds,
        private AdminNotifier $notifier,
        private NewOrderAlerts $alerts,
    ) {}

    /** Kart ödemesi doğrulandı: ayrılan stok satışa çevrilir. */
    public function markPaid(StoreOrder $order): StoreOrder
    {
        $sold = collect();
        $order = DB::transaction(function () use ($order, &$sold) {
            $order = $this->lock($order);
            if ($order->status !== 'pending_payment') {
                return $order; // ikinci geri dönüş / zamanlanmış kontrol: tekrar uygulanmaz
            }
            // Ayırma süresi dolmuş ve stok bu arada satılmışsa ödeme yine de kaydedilir; yönetici uyarılır (iade/tedarik).
            $stockNote = null;
            try {
                $sold = $this->stock->convert($order->stock_holder, 'store_order', $order->id);
            } catch (DomainException) { // yetersiz stok veya bırakılmış ayırma
                $stockNote = ' DİKKAT: ödeme süresi içinde stok tükendi; ürünleri tedarik edin veya ödemeyi iade edin.';
                $this->audit->log('store.order_paid_without_stock', $order, [], ['order_no' => $order->order_no]);
            }
            $order->forceFill(['status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
            $this->event($order, 'paid', 'Ödeme alındı.'.$stockNote);
            if ($order->cart_id) {
                StoreCartItem::where('cart_id', $order->cart_id)->delete();
            }

            return $order;
        });

        if ($order->wasChanged('status')) {
            $this->mailer->placed($order);
            $this->alerts->raise($order);
            $this->notifier->newOrder($order);
            $this->notifier->lowStock($sold);
        }

        return $order;
    }

    /** Ödeme başarısız veya terk edildi: ayrılan stok bırakılır. */
    public function markPaymentFailed(StoreOrder $order, string $reason): StoreOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = $this->lock($order);
            if ($order->status !== 'pending_payment') {
                return $order;
            }
            if ($order->stock_holder) {
                $this->stock->release($order->stock_holder);
            }
            $order->forceFill(['status' => 'payment_failed', 'payment_status' => 'failed'])->save();
            $this->voidDiscount($order);
            $this->event($order, 'payment_failed', mb_substr($reason, 0, 500));

            return $order;
        });
    }

    /**
     * Havale / EFT hesaba geçti (yönetici banka hesabında görüp onaylar): sipariş hazırlanmaya geçer.
     *
     * @throws DomainException
     */
    public function markTransferReceived(StoreOrder $order): void
    {
        $this->transition($order, ['awaiting_transfer'], 'preparing', ['payment_status' => 'paid', 'paid_at' => now(), 'approved_at' => now()],
            'Havale / EFT alındı; sipariş hazırlanıyor.', 'store.order_transfer_received');
        $order->payments()->where('driver', 'transfer')->where('status', 'initialized')->update(['status' => 'succeeded']);
        $this->mailer->transferReceived($order->fresh());
    }

    /**
     * Süresinde ödenmeyen havale siparişlerini iptal eder (stok geri döner, müşteriye e-posta gider).
     *
     * @return int iptal edilen sipariş sayısı
     */
    public function expireTransfers(): int
    {
        $count = 0;
        StoreOrder::where('status', 'awaiting_transfer')->where('created_at', '<', now()->subDays(BankTransfer::days()))
            ->orderBy('id')->each(function (StoreOrder $order) use (&$count) {
                try {
                    $this->cancel($order, 'Havale / EFT süresi içinde ödeme alınmadı.');
                    $count++;
                } catch (DomainException) {
                    // Bu arada yönetici ödemeyi onayladıysa iptal edilmez.
                }
            });

        return $count;
    }

    /** @throws DomainException */
    public function approve(StoreOrder $order): void
    {
        $this->transition($order, ['awaiting_approval'], 'preparing', ['approved_at' => now()], 'Sipariş onaylandı; hazırlanıyor.', 'store.order_approved');
    }

    /** @throws DomainException */
    public function prepare(StoreOrder $order): void
    {
        $this->transition($order, ['paid'], 'preparing', [], 'Sipariş hazırlanıyor.', 'store.order_preparing');
    }

    /** @throws DomainException */
    public function ship(StoreOrder $order, ?int $carrierId, ?string $tracking): void
    {
        $extra = ['shipped_at' => now()];
        $note = 'Teslimat için yola çıktı.';
        if ($order->delivery_method === 'cargo') {
            $carrier = $carrierId ? StoreCarrier::where('is_active', true)->find($carrierId) : null;
            $tracking = trim((string) $tracking);
            if (! $carrier || $tracking === '' || mb_strlen($tracking) > 60) {
                throw new DomainException('Kargo firmasını seçin ve takip numarasını girin.');
            }
            // Firma adı ve takip bağlantısı siparişe kopyalanır; firma sonradan değişse de sipariş etkilenmez.
            $extra += ['carrier_id' => $carrier->id, 'carrier' => $carrier->name, 'tracking_number' => $tracking, 'tracking_url' => $carrier->trackingUrl($tracking)];
            $note = "Kargoya verildi: {$carrier->name} {$tracking}";
        }

        $this->transition($order, ['paid', 'preparing'], 'shipped', $extra, $note, 'store.order_shipped');
        $this->mailer->shipped($order->fresh());
    }

    /**
     * Teslim edildi. Kapıda ödemede tahsilat türü (nakit / kapıda kart) zorunludur.
     *
     * @throws DomainException
     */
    public function deliver(StoreOrder $order, ?string $collectedMethod): void
    {
        $extra = ['delivered_at' => now()];
        if ($order->payment_method === 'cod') {
            if (! in_array($collectedMethod, ['cash', 'card'], true)) {
                throw new DomainException('Tahsilat türünü seçin (nakit veya kapıda kart).');
            }
            $extra += ['payment_status' => 'collected', 'collected_method' => $collectedMethod];
        }

        $this->transition($order, ['shipped'], 'delivered', $extra,
            $order->payment_method === 'cod' ? 'Teslim edildi, ödeme tahsil edildi ('.($collectedMethod === 'cash' ? 'nakit' : 'kapıda kart').').' : 'Teslim edildi.',
            'store.order_delivered');
    }

    /**
     * Kapıda ödemeli sipariş teslim alınmadı: stok iade edilir, telefon kapıda ödeme engel listesine girer.
     *
     * @throws DomainException
     */
    public function notDelivered(StoreOrder $order, string $reason): void
    {
        if ($order->payment_method !== 'cod') {
            throw new DomainException('Bu işlem yalnızca kapıda ödemeli siparişler içindir.');
        }

        DB::transaction(function () use ($order, $reason) {
            $this->transition($order, ['shipped'], 'not_delivered', ['payment_status' => 'void', 'cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 300)],
                'Teslim alınmadı: '.$reason, 'store.order_not_delivered');
            $this->restockAll($order, 'not-delivered');
            $this->voidDiscount($order);
            // Engel telefona ve teslimat adresine birlikte uygulanır (başka numarayla aynı adrese kapıda ödeme olmaz).
            StoreCodBlock::updateOrCreate(['phone' => $order->phone], [
                'reason' => "Sipariş {$order->order_no} teslim alınmadı: ".mb_substr($reason, 0, 200), 'order_id' => $order->id,
                'address_key' => CodPolicy::addressKey($order->shipping_district_id, $order->shipping_neighborhood, $order->shipping_address),
                'address_label' => mb_substr($order->shippingOneLine(), 0, 300),
            ]);
        });
    }

    /**
     * Ürün iadesi olmadan kısmi para iadesi (eksik gönderim, hasarlı ürün için indirim, gönül alma).
     * Kartla ödemede iyzico'ya (veya iyzico panelinden yapıldıysa "elle"), kapıda ödeme ve havalede havaleyle (elle) işlenir.
     *
     * @throws DomainException
     */
    public function refundWithoutReturn(StoreOrder $order, string $amount, string $method, string $reason, ?string $ip): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Para iadesinin nedenini yazın (müşteriye e-postada gösterilir).');
        }
        if (! $order->isSettled()) {
            throw new DomainException('Ödemesi alınmamış siparişte para iadesi yapılamaz.');
        }
        if ($order->refundsToBank()) {
            $method = 'manual';
        }
        $refund = $this->refunds->refund($order, $amount, $method, [], false, null, mb_substr($reason, 0, 300), $ip);
        $this->mailer->refunded($order->fresh(), $refund->amount, $reason);
    }

    /** Telefonu kapıda ödeme engel listesinden çıkarır. */
    public function unblockPhone(StoreCodBlock $block): void
    {
        DB::transaction(function () use ($block) {
            $block->delete();
            $this->audit->log('store.cod_unblocked', null, ['phone' => $block->phone, 'reason' => $block->reason], []);
        });
    }

    /**
     * Yola çıkmadan iptal. Kartla ödenmiş siparişte önce ödemenin tamamı iyzico üzerinden karta iade edilir;
     * iade başarısız olursa sipariş iptal edilmez. Havalesi alınmış siparişte para önce elle iade edilip kaydedilmelidir
     * (müşterinin hesabı sistemde yoktur).
     *
     * @throws DomainException
     */
    public function cancel(StoreOrder $order, string $reason, ?string $ip = null): void
    {
        $cancellable = StoreOrder::CANCELLABLE;
        if (trim($reason) === '') {
            throw new DomainException('İptal gerekçesi yazın.');
        }
        if (! in_array($order->status, $cancellable, true)) {
            throw new DomainException('Sipariş bu durumdayken bu işlem yapılamaz ('.$order->statusLabel().').');
        }
        if ($order->payment_method === 'transfer' && bccomp($order->refundableAmount(), '0', 2) > 0) {
            throw new DomainException('Havalesi alınmış sipariş: önce tutarı müşterinin hesabına iade edip "Para iadesi" ile elle kaydedin ('
                .Money::format($order->refundableAmount()).'), sonra iptal edin.');
        }

        $paidByCard = $order->payment_method === 'card' && in_array($order->payment_status, ['paid', 'partially_refunded'], true);
        if ($paidByCard && bccomp($order->refundableAmount(), '0', 2) > 0) {
            $this->refunds->refund($order, $order->refundableAmount(), 'iyzico', [], true, null, 'Sipariş iptali: '.mb_substr($reason, 0, 200), $ip);
            $order->refresh();
        }

        DB::transaction(function () use ($order, $reason, $cancellable, $paidByCard) {
            $wasPending = $order->status === 'pending_payment';
            $this->transition($order, $cancellable, 'cancelled',
                ['cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 300),
                    'payment_status' => match (true) {
                        $paidByCard, $order->payment_status === 'refunded' => 'refunded',
                        $order->payment_method === 'card' => 'failed',
                        default => 'void',
                    }],
                'İptal edildi: '.$reason, 'store.order_cancelled');

            $this->voidDiscount($order);
            if ($wasPending && $order->stock_holder) {
                $this->stock->release($order->stock_holder);
            } else {
                $this->restockAll($order, 'cancel');
            }
        });
        $this->mailer->cancelled($order->fresh());
    }

    /**
     * Müşterinin kendi iptali (kargoya verilmeden önce: onay bekliyor, havale bekleniyor, ödendi, hazırlanıyor).
     * Kartla ödenmişse tutar iyzico'dan karta iade edilir; kapıda ödeme ve ödenmemiş havale doğrudan iptal olur.
     * Havalesi alınmış siparişte para elle iade edileceği için iptal talebi kaydedilir (IBAN ile); yönetici tamamlar.
     *
     * @return bool true: iptal edildi, false: iptal talebi alındı
     *
     * @throws ValidationException|DomainException
     */
    public function cancelByCustomer(StoreOrder $order, array $input, ?string $ip): bool
    {
        $data = Validator::make($input, [
            'reason' => ['required', 'in:'.implode(',', array_keys(StoreOrder::CANCEL_REASONS))],
            'note' => ['nullable', 'string', 'max:200'],
            'iban' => ['nullable', 'string', 'max:40'],
            'account_name' => ['nullable', 'string', 'max:120'],
        ], [], ['reason' => 'iptal nedeni', 'note' => 'açıklama', 'iban' => 'IBAN', 'account_name' => 'hesap sahibi'])->validate();

        if (! $order->customerCancellable()) {
            throw new DomainException($order->cancel_requested_at ? 'İptal talebiniz alındı; işleme alınmasını bekleyin.' : 'Siparişiniz kargoya verildiği için iptal edilemez. Teslim aldıktan sonra iade talebi oluşturabilirsiniz.');
        }
        $reason = StoreOrder::CANCEL_REASONS[$data['reason']].(filled($data['note'] ?? null) ? ': '.trim($data['note']) : '');

        if ($order->payment_method === 'transfer' && bccomp($order->refundableAmount(), '0', 2) > 0) {
            $iban = Iban::normalize($data['iban'] ?? null);
            if (! $iban || blank($data['account_name'] ?? null)) {
                throw ValidationException::withMessages(['iban' => 'Ödemeniz havaleyle alındığı için iade edilecek hesabın IBAN\'ını ve hesap sahibini yazın.']);
            }
            DB::transaction(function () use ($order, $reason, $iban, $data) {
                $locked = $this->lock($order);
                if ($locked->cancel_requested_at || ! in_array($locked->status, StoreOrder::CUSTOMER_CANCELLABLE, true)) {
                    throw new DomainException('Bu sipariş için iptal talebi alınamıyor.');
                }
                $locked->forceFill(['cancel_requested_at' => now(), 'cancel_request_reason' => mb_substr($reason, 0, 300),
                    'cancel_refund_iban' => $iban, 'cancel_refund_name' => mb_substr(trim($data['account_name']), 0, 120)])->save();
                $order->setRawAttributes($locked->getAttributes(), true);
                $this->event($locked, 'cancel_request', 'Müşteri iptal istedi: '.$reason);
                $this->audit->log('store.order_cancel_requested', $locked, [], ['order_no' => $locked->order_no, 'reason' => $reason]);
            });
            $this->mailer->cancelRequested($order);
            $this->notifier->customerCancelled($order, true);

            return false;
        }

        $from = $order->status;
        $this->cancel($order, 'Müşteri iptali: '.$reason, $ip);
        $this->notifier->customerCancelled($order->refresh(), false, $from);

        return true;
    }

    /** @param  list<string>  $from */
    private function transition(StoreOrder $order, array $from, string $to, array $extra, string $note, string $auditAction): void
    {
        DB::transaction(function () use ($order, $from, $to, $extra, $note, $auditAction) {
            $locked = $this->lock($order);
            if (! in_array($locked->status, $from, true)) {
                throw new DomainException('Sipariş bu durumdayken bu işlem yapılamaz ('.$locked->statusLabel().').');
            }
            $old = $locked->status;
            $locked->forceFill(['status' => $to] + $extra)->save();
            $order->setRawAttributes($locked->getAttributes(), true);
            $this->event($locked, $to, $note);
            $this->audit->log($auditAction, $locked, ['status' => $old], ['status' => $to, 'order_no' => $locked->order_no]);
        });
    }

    /**
     * Bu sipariş için stoktan düşülmüş kalemleri geri alır (her kalem bir kez). Ödeme süresi içinde stok tükenip satış
     * hareketi oluşmadıysa (ödendi, stok yetersiz) geri alınacak bir şey yoktur.
     */
    private function restockAll(StoreOrder $order, string $reason): void
    {
        $sold = StoreStockMovement::where('reference_type', 'store_order')->where('reference_id', $order->id)
            ->whereIn('type', ['sale', 'return'])->groupBy('variant_id')->selectRaw('variant_id, -SUM(quantity) as net')->pluck('net', 'variant_id');

        foreach ($order->items()->whereNotNull('variant_id')->get() as $item) {
            $quantity = min($item->quantity, (int) ($sold[$item->variant_id] ?? 0));
            if ($quantity > 0) {
                $sold[$item->variant_id] = (int) $sold[$item->variant_id] - $quantity;
                $this->stock->restock($item->variant_id, $quantity, "order:{$order->id}:{$reason}:{$item->id}", 'store_order', $order->id,
                    "Sipariş {$order->order_no}: ".($reason === 'cancel' ? 'iptal' : 'teslim alınmadı'));
            }
        }
    }

    /** Satış gerçekleşmediyse kupon / kampanya kullanım hakkı geri verilir. */
    private function voidDiscount(StoreOrder $order): void
    {
        StoreDiscountRedemption::where('order_id', $order->id)->update(['status' => 'void']);
    }

    private function lock(StoreOrder $order): StoreOrder
    {
        return StoreOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    public function event(StoreOrder $order, string $status, ?string $note): void
    {
        $actor = $this->actor->get();
        $order->events()->create(['status' => $status, 'note' => $note, 'actor_type' => $actor['type'], 'actor_id' => $actor['id']]);
    }
}
