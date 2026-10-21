<?php

namespace App\Domain\Store\Orders;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreCartItem;
use App\Models\Store\StoreCodBlock;
use App\Models\Store\StoreOrder;
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
    public function ship(StoreOrder $order, ?string $carrier, ?string $tracking): void
    {
        $carrier = trim((string) $carrier) ?: null;
        $tracking = trim((string) $tracking) ?: null;
        if ($order->delivery_method === 'cargo' && (! $carrier || ! $tracking)) {
            throw new DomainException('Kargo firması ve takip numarası girin.');
        }

        $this->transition($order, ['paid', 'preparing'], 'shipped',
            ['shipped_at' => now(), 'carrier' => $carrier, 'tracking_number' => $tracking],
            $order->delivery_method === 'cargo' ? "Kargoya verildi: {$carrier} {$tracking}" : 'Teslimat için yola çıktı.', 'store.order_shipped');
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
     * Yola çıkmadan iptal. Kartla ödenmiş siparişlerde iade iyzico üzerinden yapılır (M7); şimdilik engellenir.
     *
     * @throws DomainException
     */
    public function cancel(StoreOrder $order, string $reason): void
    {
        if ($order->payment_status === 'paid') {
            throw new DomainException('Kartla ödenmiş sipariş, ödeme iadesiyle birlikte iptal edilmelidir (iade işlemleri bir sonraki aşamada eklenecek).');
        }
        if (trim($reason) === '') {
            throw new DomainException('İptal gerekçesi yazın.');
        }

        DB::transaction(function () use ($order, $reason) {
            $wasPending = $order->status === 'pending_payment';
            $this->transition($order, ['pending_payment', 'awaiting_approval', 'preparing'], 'cancelled',
                ['cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 300), 'payment_status' => $order->payment_method === 'cod' ? 'void' : 'failed'],
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

    /** Satılmış kalemleri stoğa geri alır (her kalem bir kez). */
    private function restockAll(StoreOrder $order, string $reason): void
    {
        foreach ($order->items()->whereNotNull('variant_id')->get() as $item) {
            $this->stock->restock($item->variant_id, $item->quantity, "order:{$order->id}:{$reason}:{$item->id}", 'store_order', $order->id,
                "Sipariş {$order->order_no}: ".($reason === 'cancel' ? 'iptal' : 'teslim alınmadı'));
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
