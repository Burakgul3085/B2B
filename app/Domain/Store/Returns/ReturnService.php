<?php

namespace App\Domain\Store\Returns;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Notifications\AdminNotifier;
use App\Domain\Store\Orders\OrderMailer;
use App\Domain\Store\Payments\RefundService;
use App\Domain\Support\Decimal;
use App\Domain\Support\Money;
use App\Domain\Support\Payments\Iban;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreReturn;
use App\Models\Store\StoreReturnItem;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * İade talepleri (cayma hakkı, docs/20-magaza-m7-raporu.md §2):
 *  - Teslimden sonra ayardaki süre (en az 14 gün) içinde, teslim edilmiş siparişte açılabilir. Siparişte aynı anda tek açık talep olur.
 *  - Her kalemden en fazla, daha önce reddedilmemiş taleplerde istenmemiş adet kadar iade istenebilir.
 *  - Kapıda ödemede para havaleyle döneceği için IBAN ve hesap sahibi zorunludur.
 *  - Teslim alınırken sağlam bulunan adetler stoğa döner. Para iadesi ancak ürün teslim alındıktan sonra yapılır.
 *  - İade tutarı sunucuda hesaplanır: ürünlerin payı; siparişteki tüm ürünler iade ediliyorsa teslimat ve kapıda ödeme bedeli de.
 */
class ReturnService
{
    public function __construct(
        private StoreStockService $stock,
        private RefundService $refunds,
        private AuditLogger $audit,
        private CurrentActor $actor,
        private OrderMailer $mailer,
        private AdminNotifier $notifier,
    ) {}

    public static function returnDays(): int
    {
        return max(14, (int) StoreSetting::get('return_days'));
    }

    /** Teslimden sonra iade süresi dolmadıysa son gün, değilse null. */
    public static function deadline(StoreOrder $order): ?Carbon
    {
        if ($order->status !== 'delivered' || ! $order->delivered_at) {
            return null;
        }
        $deadline = $order->delivered_at->copy()->addDays(self::returnDays())->endOfDay();

        return $deadline->isFuture() ? $deadline : null;
    }

    /** @return array<int, int> sipariş kalemi kimliği => hâlâ iade istenebilecek adet */
    public function returnableQuantities(StoreOrder $order): array
    {
        $requested = StoreReturnItem::query()
            ->whereIn('return_id', StoreReturn::where('order_id', $order->id)->where('status', '!=', 'rejected')->select('id'))
            ->groupBy('order_item_id')->selectRaw('order_item_id, SUM(quantity) as qty')->pluck('qty', 'order_item_id');

        // Baskı işleri (fotokopi) kişiye özel üretimdir; cayma hakkı kapsamı dışındadır, iade edilemez.
        return $order->items()->whereNull('print_job_id')->get()->mapWithKeys(fn ($item) => [$item->id => max(0, $item->quantity - (int) ($requested[$item->id] ?? 0))])->all();
    }

    /** Müşterinin talep açıp açamayacağı; açamıyorsa nedeni. */
    /** @param  bool  $byAdmin  yönetici müşteri adına açıyorsa süre sınırı uygulanmaz (teslim edilmiş olmalı) */
    public function refusal(StoreOrder $order, bool $byAdmin = false): ?string
    {
        if ($byAdmin ? $order->status !== 'delivered' : ! self::deadline($order)) {
            return 'İade talebi yalnızca teslim edilen siparişlerde, teslimden sonraki '.self::returnDays().' gün içinde açılabilir.';
        }
        if ($order->returns()->whereIn('status', StoreReturn::OPEN)->exists()) {
            return 'Bu sipariş için açık bir iade talebiniz var.';
        }
        if (array_sum($this->returnableQuantities($order)) === 0) {
            return 'Bu siparişteki tüm ürünler için iade talebi açılmış.';
        }

        return null;
    }

    /**
     * Müşterinin (veya telefonla ulaşan müşteri adına yöneticinin) iade talebi.
     *
     * @throws ValidationException|DomainException
     */
    public function request(StoreOrder $order, array $input, bool $byAdmin = false): StoreReturn
    {
        $data = Validator::make($input, [
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'reason' => ['required', 'in:'.implode(',', array_keys(StoreReturn::REASONS))],
            'note' => ['nullable', 'string', 'max:500'],
            'iban' => [$order->refundsToBank() ? 'required' : 'nullable', 'string', 'max:40'],
            'account_name' => [$order->refundsToBank() ? 'required' : 'nullable', 'string', 'max:120'],
        ], [], ['items' => 'ürünler', 'reason' => 'iade nedeni', 'note' => 'açıklama', 'iban' => 'IBAN', 'account_name' => 'hesap sahibi'])->validate();

        $iban = null;
        if ($order->refundsToBank()) {
            $iban = self::normalizeIban($data['iban']);
            if (! $iban) {
                throw ValidationException::withMessages(['iban' => 'Geçerli bir TR IBAN girin (TR ile başlayan 26 karakter).']);
            }
        }

        $actor = $this->actor->get();
        $return = DB::transaction(function () use ($order, $data, $iban, $actor, $byAdmin) {
            StoreOrder::whereKey($order->id)->lockForUpdate()->first(); // aynı siparişe eşzamanlı iki talep açılmasın
            if ($reason = $this->refusal($order, $byAdmin)) {
                throw new DomainException($reason);
            }

            $returnable = $this->returnableQuantities($order);
            $lines = array_filter(array_map('intval', $data['items']), fn ($qty) => $qty > 0);
            if ($lines === []) {
                throw ValidationException::withMessages(['items' => 'İade etmek istediğiniz ürün ve adedi seçin.']);
            }
            foreach ($lines as $itemId => $qty) {
                if (! isset($returnable[$itemId]) || $qty > $returnable[$itemId]) {
                    throw ValidationException::withMessages(['items' => 'Seçilen adet, iade edilebilecek adetten fazla.']);
                }
            }

            $return = StoreReturn::create([
                'return_no' => 'TMP-'.bin2hex(random_bytes(6)), 'order_id' => $order->id, 'status' => 'requested', 'reason' => $data['reason'],
                'customer_note' => trim((string) ($data['note'] ?? '')) ?: null,
                'refund_iban' => $iban, 'refund_account_name' => $iban ? trim($data['account_name']) : null,
                'created_by_type' => $actor['type'] ?? 'customer', 'created_by_id' => $actor['id'],
            ]);
            $return->forceFill(['return_no' => 'IA'.(100000 + $return->id)])->save();
            foreach ($lines as $itemId => $qty) {
                $return->items()->create(['order_item_id' => $itemId, 'quantity' => $qty]);
            }
            $this->event($order, "İade talebi {$return->return_no} oluşturuldu.");
            $this->audit->log('store.return_requested', $return, [], ['order_no' => $order->order_no, 'return_no' => $return->return_no]);

            return $return;
        });

        $this->mailer->returnUpdate($order, $return, 'İade talebiniz alındı',
            "{$return->return_no} numaralı iade talebinizi aldık. İnceledikten sonra size e-postayla bilgi vereceğiz.");
        if (! $byAdmin) {
            $this->notifier->returnRequested($return, $order);
        }

        return $return;
    }

    /** @throws DomainException */
    public function approve(StoreReturn $return, ?string $note): void
    {
        $note = trim((string) $note) ?: null;
        $this->transition($return, 'requested', 'approved', ['approved_at' => now(), 'admin_note' => $note], 'store.return_approved');

        $address = StoreSetting::get('return_address') ?: StoreSetting::get('address');
        $this->mailer->returnUpdate($this->order($return), $return, 'İade talebiniz onaylandı',
            "{$return->return_no} numaralı iade talebiniz onaylandı. Ürünleri, faturası veya sipariş numarası ile birlikte aşağıdaki adrese gönderebilirsiniz."
            .($address ? " İade adresi: {$address}." : '').($note ? " Not: {$note}" : ''));
    }

    /** @throws DomainException */
    public function reject(StoreReturn $return, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DomainException('Ret gerekçesi yazın.');
        }
        $this->transition($return, 'requested', 'rejected', ['admin_note' => mb_substr(trim($reason), 0, 500), 'closed_at' => now()], 'store.return_rejected');
        $this->mailer->returnUpdate($this->order($return), $return, 'İade talebiniz hakkında',
            "{$return->return_no} numaralı iade talebiniz onaylanmadı. Gerekçe: {$reason}");
    }

    /**
     * Ürün teslim alındı; sağlam adetler stoğa döner.
     *
     * @param  array<int, int>  $resellable  iade kalemi kimliği => sağlam adet
     *
     * @throws DomainException
     */
    public function receive(StoreReturn $return, array $resellable): void
    {
        DB::transaction(function () use ($return, $resellable) {
            $this->transition($return, 'approved', 'received', ['received_at' => now()], 'store.return_received');
            foreach ($return->items()->with('orderItem')->get() as $item) {
                $qty = max(0, min($item->quantity, (int) ($resellable[$item->id] ?? 0)));
                $item->update(['restocked_quantity' => $qty]);
                if ($qty > 0 && $item->orderItem->variant_id) {
                    $this->stock->restock($item->orderItem->variant_id, $qty, "return:{$return->id}:{$item->id}", 'store_return', $return->id,
                        "İade {$return->return_no} (sipariş {$this->order($return)->order_no})");
                }
            }
        });
    }

    /** Ürünlerin payı + (tüm ürünler iade ediliyorsa) teslimat ve kapıda ödeme bedeli; iade edilebilecek kalan tutarla sınırlı. */
    public function maxRefund(StoreReturn $return): string
    {
        $order = $this->order($return);
        $amount = '0.00';
        foreach ($return->items()->with('orderItem')->get() as $item) {
            $paid = bcsub((string) $item->orderItem->line_total, (string) $item->orderItem->discount_amount, 2); // indirim payı düşülür
            $share = bcdiv(bcmul($paid, (string) $item->quantity, 4), (string) $item->orderItem->quantity, 4);
            $amount = bcadd($amount, Decimal::round($share, 2), 2);
        }
        if ($this->completesOrder($return)) {
            $amount = bcadd($amount, bcadd((string) $order->shipping_fee, (string) $order->cod_fee, 2), 2);
        }
        $refundable = $order->refundableAmount();

        return bccomp($amount, $refundable, 2) > 0 ? $refundable : $amount;
    }

    /**
     * Para iadesi. Kartla ödemede iyzico'ya, kapıda ödemede elle (havale yapıldıktan sonra) işlenir.
     *
     * @throws DomainException
     */
    public function refund(StoreReturn $return, string $amount, ?string $note, ?string $ip): void
    {
        // Aynı iade iki kez ödenmesin: durum denetimi ve para iadesi aynı kilit altında.
        $lock = Cache::lock("store-return:{$return->id}", 90);
        if (! $lock->get()) {
            throw new DomainException('Bu iade için başka bir işlem sürüyor. Biraz sonra tekrar deneyin.');
        }

        try {
            $return->refresh();
            if ($return->status !== 'received') {
                throw new DomainException('Para iadesi, ürün teslim alındıktan sonra yapılır.');
            }
            $parsed = Decimal::parse($amount, 2);
            if ($parsed === null || bccomp($parsed, '0', 2) <= 0 || bccomp($parsed, $this->maxRefund($return), 2) > 0) {
                throw new DomainException('İade tutarı sıfırdan büyük ve en fazla '.Money::format($this->maxRefund($return)).' olabilir.');
            }

            $order = $this->order($return);
            $method = $order->payment_method === 'card' ? 'iyzico' : 'manual';
            $quantities = $return->items()->get()->mapWithKeys(fn ($i) => [$i->order_item_id => $i->quantity])->all();
            $this->refunds->refund($order, $parsed, $method, $quantities, $this->completesOrder($return), $return, $note, $ip);
            $this->transition($return, 'received', 'refunded', ['refund_amount' => $parsed, 'refunded_at' => now(), 'closed_at' => now()], 'store.return_refunded');
        } finally {
            $lock->release();
        }
        $this->mailer->returnUpdate($order, $return, 'İadeniz yapıldı', $method === 'iyzico'
            ? Money::format($parsed).' tutarındaki iadeniz kartınıza gönderildi. Tutarın hesabınıza yansıması bankanıza göre birkaç gün sürebilir.'
            : Money::format($parsed).' tutarındaki iadeniz bildirdiğiniz banka hesabına gönderildi.');
    }

    /** Bu iadeyle siparişteki tüm ürünler iade edilmiş olur mu? (Reddedilenler hariç tüm talepler.) */
    private function completesOrder(StoreReturn $return): bool
    {
        return array_sum($this->returnableQuantities($this->order($return))) === 0;
    }

    /** @throws DomainException */
    private function transition(StoreReturn $return, string $from, string $to, array $extra, string $auditAction): void
    {
        DB::transaction(function () use ($return, $from, $to, $extra, $auditAction) {
            $locked = StoreReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== $from) {
                throw new DomainException('İade talebi bu durumdayken bu işlem yapılamaz ('.$locked->statusLabel().').');
            }
            $locked->forceFill(['status' => $to] + $extra)->save();
            $return->setRawAttributes($locked->getAttributes(), true);
            $order = $this->order($return);
            $this->event($order, "İade {$return->return_no}: ".StoreReturn::STATUSES[$to].'.');
            $this->audit->log($auditAction, $return, ['status' => $from], ['status' => $to, 'order_no' => $order->order_no, 'return_no' => $return->return_no]);
        });
    }

    private function order(StoreReturn $return): StoreOrder
    {
        return $return->loadMissing('order')->order;
    }

    private function event(StoreOrder $order, string $note): void
    {
        $actor = $this->actor->get();
        $order->events()->create(['status' => 'return', 'note' => $note, 'actor_type' => $actor['type'], 'actor_id' => $actor['id']]);
    }

    /** "TR12 0006 ..." → "TR120006..." ; geçersizse null (ISO 13616, mod 97). */
    public static function normalizeIban(?string $raw): ?string
    {
        return Iban::normalize($raw);
    }
}
