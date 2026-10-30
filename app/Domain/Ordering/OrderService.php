<?php

namespace App\Domain\Ordering;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Inventory\InsufficientStock;
use App\Domain\Inventory\Quantity;
use App\Domain\Inventory\StockService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Payments\OrderPaymentService;
use App\Domain\Payments\PaymentSettings;
use App\Domain\Support\LocalTime;
use App\Domain\Support\Money;
use App\Domain\Tenancy\TenantContext;
use App\Models\BusinessCounter;
use App\Models\BusinessSetting;
use App\Models\CartItem;
use App\Models\DealerAddress;
use App\Models\DealerRelationship;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Sipariş yaşam döngüsü (docs/03-is-kurallari.md §3).
 *
 * Garantiler:
 *  - Onay ya tamamen olur (durum + stok + cari) ya da hiç olmaz.
 *  - Aynı sipariş iki kez onaylanamaz (satır kilidi + durum + stock_applied_at + source_key UNIQUE).
 *  - Eşzamanlı onaylarda fazla satış olmaz (stok satırı kilidi, StockService::applyMany).
 *  - İptal, stok ve cariye en fazla bir kez ters kayıt yazar; kartla ödenmiş siparişte iade alınamazsa iptal olmaz.
 *
 * Ödeme (ödeme sistemi açıksa): bayi yöntemi sepette seçer, onaydan sonra onaylanan tutarı öder. Kart / havale
 * siparişi ödeme gelmeden hazırlanmaz; ödeme bekleyen sipariş tamamlanamaz.
 */
class OrderService
{
    public function __construct(
        private TenantContext $tenant,
        private CartService $carts,
        private StockService $stock,
        private LedgerService $ledger,
        private AuditLogger $audit,
        private CurrentActor $actor,
        private PaymentSettings $paymentSettings,
        private OrderPaymentService $payments,
    ) {}

    /**
     * Bayinin sepetinden sipariş oluşturur. Stok kontrolü bilgilendiricidir (rezervasyon yok);
     * fazla satışı engelleyen kontrol onaydadır. Ödeme sistemi açıksa bayinin izinli yöntemlerinden biri seçilmelidir.
     */
    public function submit(DealerRelationship $relationship, int $addressId, ?string $note, string $submitToken, ?string $paymentMethod = null): Order
    {
        if ($existing = Order::where('submit_token', $submitToken)->first()) {
            return $existing; // çift tıklama / ağ tekrarı: aynı sipariş döner
        }

        return DB::transaction(function () use ($relationship, $addressId, $note, $submitToken, $paymentMethod) {
            // İlişki satırı kilidi aynı bayinin eşzamanlı gönderimlerini sıraya sokar; token kilitten sonra yeniden kontrol edilir.
            $relationship = DealerRelationship::with(['business', 'dealer.user'])->whereKey($relationship->id)->lockForUpdate()->firstOrFail();
            if ($existing = Order::where('submit_token', $submitToken)->first()) {
                return $existing;
            }
            if (! $relationship->isActive() || ! $relationship->business->isActive() || $relationship->dealer->user?->status !== 'active') {
                throw new DomainException('Hesabınız sipariş vermeye kapalı. Lütfen toptancınızla iletişime geçin.');
            }

            $methods = $this->paymentSettings->methodsFor($relationship);
            if ($methods !== [] && ! isset($methods[$paymentMethod])) {
                throw new DomainException('Ödeme yöntemi seçin.');
            }

            $address = DealerAddress::where('dealer_relationship_id', $relationship->id)->whereNull('archived_at')->find($addressId)
                ?? throw new DomainException('Geçerli bir sevk adresi seçin.');

            $warehouseId = (int) BusinessSetting::where('business_id', $this->tenant->businessId())->value('default_warehouse_id');
            $cart = $this->carts->cart($relationship);
            CartItem::where('cart_id', $cart->id)->lockForUpdate()->get();

            $summary = $this->carts->summary($relationship, $warehouseId);
            if ($summary['lines'] === []) {
                throw new DomainException('Sepetiniz boş.');
            }
            if ($summary['has_issues']) {
                throw new DomainException('Sepetinizde düzeltilmesi gereken ürünler var.');
            }

            $totals = $this->withDeliveryFee($summary['totals']);
            $order = Order::create([
                'order_no' => $this->nextOrderNo(),
                'dealer_relationship_id' => $relationship->id,
                'status' => 'submitted',
                'submit_token' => $submitToken,
                'warehouse_id' => $warehouseId,
                ...$totals,
                'payment_method' => $methods === [] ? null : $paymentMethod,
                'dealer_note' => $note,
                'ship_address_id' => $address->id,
                'ship_title' => $address->title,
                'ship_recipient' => $address->recipient_name,
                'ship_phone' => $address->phone,
                'ship_city' => $address->city,
                'ship_district' => $address->district,
                'ship_address' => $address->address_line,
                'ship_note' => $address->note,
                'delivery_recipient' => $address->recipient_name,
                'delivery_phone' => $address->phone,
            ]);

            foreach ($summary['lines'] as $line) {
                $p = $line['product'];
                $price = $line['price'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $p->id,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'name' => $p->name.($p->variantLabel() ? ' ('.$p->variantLabel().')' : ''),
                    'unit_name' => $p->unit->name,
                    'vat_rate' => $price->taxRate,
                    'price_source' => $price->baseSource,
                    'discount_name' => $price->discountName,
                    'quantity_requested' => (string) $line['item']->quantity,
                    'list_unit_price' => $price->listPrice,
                    'unit_price' => $price->basePrice,
                    'discount_percent' => $price->discountPercent,
                    ...$line['amounts'],
                ]);
            }

            CartItem::where('cart_id', $cart->id)->delete();
            $this->history($order, null, 'submitted');
            $this->audit->log('order.submitted', $order, [], ['order_no' => $order->order_no, 'grand_total' => (string) $order->grand_total]);

            return $order;
        });
    }

    public function markInReview(Order $order): void
    {
        $this->simpleTransition($order, 'in_review');
    }

    public function markPreparing(Order $order): void
    {
        if ($order->awaitingPayment() && in_array($order->payment_method, ['card', 'transfer'], true)) {
            throw new DomainException('Bayinin ödemesi alınmadan sipariş hazırlanamaz. Havale geldiyse önce "Ödeme alındı" ile kaydedin.');
        }
        $this->simpleTransition($order, 'preparing');
    }

    /**
     * Toptancı onayı (§3.3). $approvedQty: [order_item_id => miktar]; istenenden az girilirse kısmi onaydır (K2).
     *
     * @throws InsufficientStock yetersiz kalem varsa (hiçbir değişiklik yapılmaz)
     */
    public function approve(Order $order, array $approvedQty, int $warehouseId, array $delivery, int $userId): Order
    {
        return DB::transaction(function () use ($order, $approvedQty, $warehouseId, $delivery, $userId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            OrderStateMachine::assert($order->status, 'approved');
            if (! in_array($order->status, Order::PENDING, true) || $order->stock_applied_at !== null) {
                throw new DomainException('Sipariş zaten işlenmiş.');
            }

            $relationship = $order->relationship()->with('business')->first();
            if (! $relationship->isActive()) {
                throw new DomainException('Bayi askıda veya kapalı; sipariş onaylanamaz. Önce bayinin durumunu kontrol edin veya siparişi reddedin.');
            }

            $warehouse = Warehouse::where('is_active', true)->find($warehouseId) ?? throw new DomainException('Geçerli ve aktif bir depo seçin.');
            $items = $order->items()->with('product.unit')->get();

            $stockLines = [];
            $isPartial = false;
            foreach ($items as $item) {
                $qty = Quantity::parse($approvedQty[$item->id] ?? (string) $item->quantity_requested);
                if ($qty === null || bccomp($qty, '0', 3) < 0 || bccomp($qty, (string) $item->quantity_requested, 3) > 0) {
                    throw new DomainException("\"{$item->name}\" için onaylanan miktar 0 ile istenen miktar arasında olmalıdır.");
                }
                if (! $item->product->unit->allows_fraction && ! Quantity::isWhole($qty)) {
                    throw new DomainException("\"{$item->name}\" için küsuratlı miktar onaylanamaz.");
                }
                $isPartial = $isPartial || bccomp($qty, (string) $item->quantity_requested, 3) < 0;
                $item->setAttribute('quantity_approved', $qty);

                if (bccomp($qty, '0', 3) > 0) {
                    $stockLines[] = ['product' => $item->product, 'qty' => Quantity::negate($qty), 'source_key' => "order:{$order->id}:sale:{$item->id}"];
                }
            }

            if ($stockLines === []) {
                throw new DomainException('En az bir kalem için sıfırdan büyük miktar onaylanmalıdır; tamamen karşılanamıyorsa siparişi reddedin.');
            }

            // Stok: tüm kalemler kilitlenip kontrol edilir; biri yetersizse hiçbir şey yazılmaz.
            $this->stock->applyMany($warehouse->id, 'sale', $stockLines, ['reference_type' => 'order', 'reference_id' => $order->id]);

            // Tutarlar onaylanan miktarla, SİPARİŞTEKİ fiyat ve KDV ile yeniden hesaplanır.
            foreach ($items as $item) {
                $item->fill(LineCalculator::line((string) $item->unit_price, (string) $item->quantity_approved, (string) $item->discount_percent, (string) $item->vat_rate))->save();
            }

            $from = $order->status;
            $requested = (string) $order->grand_total;
            $order->fill($this->withDeliveryFee(LineCalculator::totals($items->map->only(['line_subtotal', 'line_discount', 'line_net', 'line_vat', 'line_total']))));
            $order->fill([
                'requested_total' => $requested,
                // Onaylanan tutar ödenir: kart, havale ve teslimde ödemede ödeme beklenir; açık hesapta beklenmez.
                'payment_status' => in_array($order->payment_method, ['card', 'transfer', 'on_delivery'], true) ? 'awaiting' : null,
                'status' => 'approved',
                'is_partial' => $isPartial,
                'warehouse_id' => $warehouse->id,
                'approved_at' => now(),
                'approved_by' => $userId,
                'stock_applied_at' => now(),
                'delivery_recipient' => $delivery['recipient'] ?? $order->delivery_recipient,
                'delivery_phone' => $delivery['phone'] ?? $order->delivery_phone,
                'delivery_note' => $delivery['note'] ?? null,
                'delivery_planned_on' => $delivery['planned_on'] ?? null,
            ])->save();

            if ($this->ledgerPostOn() === 'approval') {
                $this->ledger->postOrderDebit($order);
                $order->forceFill(['ledger_applied_at' => now()])->save();
            }

            $this->history($order, $from, 'approved', $isPartial ? 'Kısmi onay' : null);
            $this->audit->log('order.approved', $order, ['status' => $from], [
                'grand_total' => (string) $order->grand_total, 'is_partial' => $isPartial, 'warehouse_id' => $warehouse->id,
            ]);

            return $order;
        });
    }

    public function reject(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            OrderStateMachine::assert($order->status, 'rejected');

            $from = $order->status;
            $order->update(['status' => 'rejected', 'rejected_reason' => $reason]);
            $this->history($order, $from, 'rejected', $reason);
            $this->audit->log('order.rejected', $order, ['status' => $from], ['reason' => $reason]);

            return $order;
        });
    }

    public function complete(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            OrderStateMachine::assert($order->status, 'completed');
            if ($order->awaitingPayment()) {
                throw new DomainException('Ödemesi alınmamış sipariş tamamlanamaz. Ödemeyi aldıysanız önce "Ödeme alındı" ile kaydedin.');
            }

            if ($this->ledgerPostOn() === 'completion' && $order->ledger_applied_at === null) {
                $this->ledger->postOrderDebit($order);
                $order->ledger_applied_at = now();
            }

            $from = $order->status;
            $order->fill(['status' => 'completed', 'completed_at' => now()])->save();
            $this->history($order, $from, 'completed');
            $this->audit->log('order.completed', $order, ['status' => $from]);

            return $order;
        });
    }

    /** Bayinin iptali: onay öncesi doğrudan; onaydan sonra toptancıya iptal talebi. */
    public function cancelByDealer(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($order->status, Order::PENDING, true)) {
                return $this->doCancel($order, $reason);
            }

            OrderStateMachine::assert($order->status, 'cancel_requested');
            $from = $order->status;
            $order->update(['status' => 'cancel_requested', 'cancel_requested_from' => $from, 'cancel_reason' => $reason]);
            $this->history($order, $from, 'cancel_requested', $reason);
            $this->audit->log('order.cancel_requested', $order, ['status' => $from], ['reason' => $reason]);

            return $order;
        });
    }

    /** Toptancı iptal talebini kabul eder (ters kayıtlar) veya reddeder (önceki duruma döner). */
    public function resolveCancelRequest(Order $order, bool $accept, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $accept, $note) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'cancel_requested') {
                throw new DomainException('Bu sipariş için bekleyen iptal talebi yok.');
            }

            if ($accept) {
                return $this->doCancel($order, $order->cancel_reason ?? 'Bayi talebi');
            }

            $back = $order->cancel_requested_from ?? 'approved';
            OrderStateMachine::assert('cancel_requested', $back);
            $order->update(['status' => $back, 'cancel_requested_from' => null]);
            $this->history($order, 'cancel_requested', $back, $note ? 'İptal talebi reddedildi: '.$note : 'İptal talebi reddedildi');
            $this->audit->log('order.cancel_request_declined', $order, [], ['note' => $note]);

            return $order;
        });
    }

    public function cancelByBusiness(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            return $this->doCancel($order, $reason);
        });
    }

    /** İptal ve gerekiyorsa ters kayıtlar. Sipariş satırı kilitli, transaction içinde çağrılır. */
    private function doCancel(Order $order, string $reason): Order
    {
        OrderStateMachine::assert($order->status, 'cancelled');

        if ($order->stock_applied_at !== null && $order->stock_reversed_at === null) {
            $lines = $order->items()->with('product')->get()
                ->filter(fn ($i) => bccomp((string) $i->quantity_approved, '0', 3) > 0)
                ->map(fn ($i) => ['product' => $i->product, 'qty' => (string) $i->quantity_approved, 'source_key' => "order:{$order->id}:sale_reversal:{$i->id}"])
                ->values()->all();
            $this->stock->applyMany($order->warehouse_id, 'sale_reversal', $lines, ['reference_type' => 'order', 'reference_id' => $order->id]);
            $order->stock_reversed_at = now();
        }

        if ($order->ledger_applied_at !== null && $order->ledger_reversed_at === null) {
            $this->ledger->reverseOrderDebit($order, $reason);
            $order->ledger_reversed_at = now();
        }

        // Kart ödemesi iyzico'dan iade edilir (alınamazsa istisna: iptal geri alınır); havale / nakit ödeme carisinde alacak kalır.
        $kept = $this->payments->settleCancellation($order, $reason);

        $from = $order->status;
        $order->fill(['status' => 'cancelled', 'cancel_reason' => $reason, 'cancelled_at' => now(), 'cancel_requested_from' => null])->save();
        $this->history($order, $from, 'cancelled', $reason.($kept ? ' · Alınan '.Money::format($kept).' ödeme bayinin carisinde alacak olarak duruyor.' : ''));
        $this->audit->log('order.cancelled', $order, ['status' => $from], [
            'reason' => $reason, 'stock_reversed' => $order->stock_reversed_at !== null, 'ledger_reversed' => $order->ledger_reversed_at !== null,
        ]);

        return $order;
    }

    private function simpleTransition(Order $order, string $to): void
    {
        DB::transaction(function () use ($order, $to) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            OrderStateMachine::assert($locked->status, $to);
            $from = $locked->status;
            $locked->update(['status' => $to]);
            $this->history($locked, $from, $to);
            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function history(Order $order, ?string $from, string $to, ?string $note = null): void
    {
        $actor = $this->actor->get();
        OrderStatusHistory::create([
            'order_id' => $order->id, 'from_status' => $from, 'to_status' => $to,
            'actor_type' => $actor['type'], 'actor_id' => $actor['id'], 'note' => $note,
        ]);
    }

    /** Teslimat ücreti (ayarlıysa) KDV dahil genel toplama eklenir. */
    private function withDeliveryFee(array $totals): array
    {
        $fee = $this->paymentSettings->deliveryFee((string) $totals['grand_total']);

        return ['delivery_fee' => $fee, 'grand_total' => bcadd((string) $totals['grand_total'], $fee, 2)] + $totals;
    }

    private function ledgerPostOn(): string
    {
        return BusinessSetting::where('business_id', $this->tenant->businessId())->value('ledger_post_on') ?? 'approval';
    }

    /** SIP-2026-000001 biçiminde, işletme ve yıl bazında kesintisiz sıra. */
    private function nextOrderNo(): string
    {
        $year = LocalTime::now()->year;
        $key = "order:{$year}";
        $businessId = $this->tenant->businessId();

        DB::statement('INSERT INTO business_counters (business_id, `key`, value) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE id = id', [$businessId, $key]);
        $counter = BusinessCounter::where('key', $key)->lockForUpdate()->firstOrFail();
        $counter->increment('value');

        $prefix = BusinessSetting::where('business_id', $businessId)->value('order_no_prefix') ?: 'SIP';

        return sprintf('%s-%d-%06d', $prefix, $year, $counter->value);
    }
}
