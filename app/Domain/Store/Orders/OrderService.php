<?php

namespace App\Domain\Store\Orders;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Payments\RefundService;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreCartItem;
use App\Models\Store\StoreCodBlock;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreStockMovement;
use DomainException;
use Illuminate\Support\Facades\DB;

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
    ) {}

    /** Kart ödemesi doğrulandı: ayrılan stok satışa çevrilir. */
    public function markPaid(StoreOrder $order): StoreOrder
    {
        $order = DB::transaction(function () use ($order) {
            $order = $this->lock($order);
            if ($order->status !== 'pending_payment') {
                return $order; // ikinci geri dönüş / zamanlanmış kontrol: tekrar uygulanmaz
            }
            // Ayırma süresi dolmuş ve stok bu arada satılmışsa ödeme yine de kaydedilir; yönetici uyarılır (iade/tedarik).
            $stockNote = null;
            try {
                $this->stock->convert($order->stock_holder, 'store_order', $order->id);
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
            $this->event($order, 'payment_failed', mb_substr($reason, 0, 500));

            return $order;
        });
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
            StoreCodBlock::firstOrCreate(['phone' => $order->phone], ['reason' => "Sipariş {$order->order_no} teslim alınmadı: ".mb_substr($reason, 0, 200), 'order_id' => $order->id]);
        });
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
     * iade başarısız olursa sipariş iptal edilmez.
     *
     * @throws DomainException
     */
    public function cancel(StoreOrder $order, string $reason, ?string $ip = null): void
    {
        $cancellable = ['pending_payment', 'awaiting_approval', 'paid', 'preparing'];
        if (trim($reason) === '') {
            throw new DomainException('İptal gerekçesi yazın.');
        }
        if (! in_array($order->status, $cancellable, true)) {
            throw new DomainException('Sipariş bu durumdayken bu işlem yapılamaz ('.$order->statusLabel().').');
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
                    'payment_status' => $paidByCard ? 'refunded' : ($order->payment_method === 'cod' ? 'void' : 'failed')],
                'İptal edildi: '.$reason, 'store.order_cancelled');

            if ($wasPending && $order->stock_holder) {
                $this->stock->release($order->stock_holder);
            } else {
                $this->restockAll($order, 'cancel');
            }
        });
        $this->mailer->cancelled($order->fresh());
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
