<?php

namespace App\Domain\Store\Returns;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Files\ImageUploader;
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
use App\Models\Store\StoreReturnPhoto;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * İade talepleri (cayma hakkı, docs/20-magaza-m7-raporu.md §2):
 *  - Teslimden sonra ayardaki süre (en az 14 gün) içinde, teslim edilmiş siparişte açılabilir. Siparişte aynı anda tek açık talep olur.
 *  - Her kalemden en fazla, daha önce reddedilmemiş (ve süresi dolmamış) taleplerde istenmemiş adet kadar iade istenebilir.
 *  - Kapıda ödemede para havaleyle döneceği için IBAN ve hesap sahibi zorunludur.
 *  - Hasarlı / yanlış / açıklamaya uymayan üründe en az bir fotoğraf zorunludur (en fazla 5); fotoğraflar özel diskte tutulur.
 *  - Onaydan sonra müşteri ürünü kargolar ve kargo bilgisini girer (shipped). Sözleşme gereği mağazanın bildirdiği kargo
 *    firmasıyla yapılan iadede masraf mağazaya aittir: anlaşma kodu ayarlıysa müşteri o firmayla ücretsiz gönderir (başka
 *    firmayla gönderirse ücret kendisine aittir); kod yoksa gönderici ödemeli gönderir, yazdığı ücret iade tutarına eklenir.
 *  - Müşteri ürünü talepten itibaren SHIP_DAYS (10) gün içinde gönderir; REMIND_DAYS günde kargo bilgisi yoksa hatırlatma,
 *    EXPIRE_DAYS günde talep kapanır.
 *  - Teslim alınırken sağlam bulunan adetler stoğa döner. Para iadesi ancak ürün teslim alındıktan sonra yapılır;
 *    yönetici "Teslim alındı, iade et" ile ikisini tek adımda yapar.
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
        private ImageUploader $images,
    ) {}

    public const PRINT_ONLY = 'Fotokopi ve baskılar kişiye özel üretildiği için iade edilemez. Baskı hatası varsa bize ulaşın, ücretsiz yeniden basalım.';

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
            ->whereIn('return_id', StoreReturn::where('order_id', $order->id)->whereNotIn('status', ['rejected', 'expired'])->select('id'))
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
        if (! $order->items()->whereNull('print_job_id')->exists()) {
            return self::PRINT_ONLY;
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
            'photos' => [in_array($input['reason'] ?? null, StoreReturn::SELLER_FAULT, true) && ! $byAdmin ? 'required' : 'nullable', 'array', 'max:'.StoreReturn::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'photos.required' => 'Bu iade nedeninde ürünün fotoğrafını ekleyin (en az 1).',
            'photos.max' => 'En fazla '.StoreReturn::MAX_PHOTOS.' fotoğraf ekleyebilirsiniz.',
            'photos.*.mimes' => 'Fotoğraflar JPG, PNG veya WEBP olmalıdır.',
            'photos.*.max' => 'Her fotoğraf en fazla 10 MB olabilir.',
        ], ['items' => 'ürünler', 'reason' => 'iade nedeni', 'note' => 'açıklama', 'iban' => 'IBAN', 'account_name' => 'hesap sahibi', 'photos' => 'fotoğraflar'])->validate();

        $iban = null;
        if ($order->refundsToBank()) {
            $iban = self::normalizeIban($data['iban']);
            if (! $iban) {
                throw ValidationException::withMessages(['iban' => 'Geçerli bir TR IBAN girin (TR ile başlayan 26 karakter).']);
            }
        }

        // Fotoğraflar önce kaydedilir (yeniden kodlanır, özel disk); talep oluşturulamazsa silinir.
        $photos = [];
        try {
            foreach ($data['photos'] ?? [] as $file) {
                $path = $this->images->store($file, 'store/returns', 1600, 10 * 1024 * 1024);
                $photos[] = ['path' => $path, 'size' => (int) Storage::disk('local')->size($path)];
            }
        } catch (DomainException $e) {
            array_map(fn ($p) => $this->images->delete($p['path']), $photos);
            throw ValidationException::withMessages(['photos' => $e->getMessage()]);
        }

        $actor = $this->actor->get();
        try {
            $return = $this->createRequest($order, $data, $iban, $actor, $byAdmin, $photos);
        } catch (\Throwable $e) {
            array_map(fn ($p) => $this->images->delete($p['path']), $photos);
            throw $e;
        }

        $this->mailer->returnUpdate($order, $return, 'İade talebiniz alındı',
            "{$return->return_no} numaralı iade talebinizi aldık. İnceledikten sonra size e-postayla bilgi vereceğiz.");
        if (! $byAdmin) {
            $this->notifier->returnRequested($return, $order);
        }

        return $return;
    }

    private function createRequest(StoreOrder $order, array $data, ?string $iban, array $actor, bool $byAdmin, array $photos): StoreReturn
    {
        return DB::transaction(function () use ($order, $data, $iban, $actor, $byAdmin, $photos) {
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
            foreach ($photos as $photo) {
                $return->photos()->create($photo + ['created_at' => now()]);
            }
            $this->event($order, "İade talebi {$return->return_no} oluşturuldu.");
            $this->audit->log('store.return_requested', $return, [], ['order_no' => $order->order_no, 'return_no' => $return->return_no]);

            return $return;
        });
    }

    /** @throws DomainException */
    public function approve(StoreReturn $return, ?string $note): void
    {
        $note = trim((string) $note) ?: null;
        $this->transition($return, 'requested', 'approved', ['approved_at' => now(), 'admin_note' => $note], 'store.return_approved');

        $this->mailer->returnUpdate($this->order($return), $return, 'İade talebiniz onaylandı',
            "{$return->return_no} numaralı iade talebiniz onaylandı. ".self::shippingInstructions($return)
            .' Gönderdikten sonra sipariş sayfanızdaki iade bölümüne kargo firmasını ve takip numarasını yazın.'.($note ? " Not: {$note}" : ''));
    }

    /** İade kargosu talimatı (onay e-postası ve müşterinin sipariş sayfası). */
    public static function shippingInstructions(StoreReturn $return): string
    {
        $address = StoreSetting::get('return_address') ?: StoreSetting::get('address');
        $deadline = $return->shipDeadline()->timezone('Europe/Istanbul')->format('d.m.Y');
        $code = StoreReturn::returnCode();

        return 'Ürünleri, kutusuna iade numarasını ('.$return->return_no.') yazarak'.($address ? " şu adrese gönderin: {$address}." : ' iade adresimize gönderin.')
            .($code
                ? " {$code['carrier']} şubesine {$code['code']} iade anlaşma koduyla ücretsiz gönderebilirsiniz; başka bir kargo firmasıyla gönderirseniz kargo ücreti size aittir."
                : ' Kargo şubesinden gönderici ödemeli gönderin ve ödediğiniz kargo ücretini kargo bilgisiyle birlikte yazın; bu tutar iadenize eklenir.')
            ." Ürünleri en geç {$deadline} tarihine kadar kargoya verin.";
    }

    /**
     * Müşteri iade kargosunu girdi (onaylanan talep). Mağazadan kaynaklı nedenlerde ödediği kargo ücreti de yazılır.
     *
     * @throws ValidationException|DomainException
     */
    public function ship(StoreReturn $return, array $input): void
    {
        $data = Validator::make($input, [
            'cargo_company' => ['required', 'string', 'max:40'],
            'cargo_tracking' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-\s]+$/'],
            'cargo_fee' => ['nullable', 'string', 'max:12'],
        ], ['cargo_tracking.regex' => 'Takip numarası yalnızca harf, rakam ve tire içerebilir.'], ['cargo_company' => 'kargo firması', 'cargo_tracking' => 'takip numarası', 'cargo_fee' => 'kargo ücreti'])->validate();

        $fee = null;
        if (StoreReturn::returnCode() === null && filled($data['cargo_fee'] ?? null)) {
            $fee = Decimal::parse($data['cargo_fee'], 2);
            if ($fee === null || bccomp($fee, '0', 2) < 0 || bccomp($fee, '1000', 2) > 0) {
                throw ValidationException::withMessages(['cargo_fee' => 'Ödediğiniz kargo ücretini yazın (ör. 85,00).']);
            }
        }

        $this->transition($return, 'approved', 'shipped', [
            'shipped_at' => now(), 'cargo_company' => trim($data['cargo_company']), 'cargo_tracking' => strtoupper(preg_replace('/\s+/', '', $data['cargo_tracking'])),
            'cargo_fee' => $fee,
        ], 'store.return_shipped');
        $this->notifier->returnShipped($return, $this->order($return));
    }

    /** Müşterinin sipariş sayfası için fotoğraf adresleri (imzalı, 1 saat). @return list<string> */
    public static function photoUrls(StoreReturn $return): array
    {
        return $return->photos->map(fn (StoreReturnPhoto $p) => URL::temporarySignedRoute('store.return-photo', now()->addHour(), ['photo' => $p->id]))->all();
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
            $this->transition($return, ['approved', 'shipped'], 'received', ['received_at' => now()], 'store.return_received');
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
        if ($return->cargo_fee !== null) {
            $amount = bcadd($amount, (string) $return->cargo_fee, 2); // müşterinin ödediği iade kargosu (anlaşma kodu yokken)
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

    /**
     * Tek adımda: ürünler teslim alınır (sağlam adetler stoğa döner) ve para iade edilir. İade başarısız olursa ürün
     * teslim alınmış kalır; hata gösterilir ve para iadesi ayrıca yeniden denenebilir.
     *
     * @param  array<int, int>  $resellable
     *
     * @throws DomainException
     */
    public function receiveAndRefund(StoreReturn $return, array $resellable, string $amount, ?string $note, ?string $ip): void
    {
        if (! $this->order($return)->isSettled()) {
            throw new DomainException('Siparişin ödemesi alınmamış; önce ürünleri teslim alın, iadeyi ödeme durumuna göre yapın.');
        }
        $this->receive($return, $resellable);
        try {
            $this->refund($return->fresh(), $amount, $note, $ip);
        } catch (DomainException $e) {
            throw new DomainException('Ürünler teslim alındı ve stoğa işlendi, ancak para iadesi yapılamadı: '.$e->getMessage().' Para iadesini aşağıdan yeniden deneyin.');
        }
    }

    /**
     * Onaylanıp süresinde kargolanmayan talepler: REMIND_DAYS günde bir kez hatırlatma, EXPIRE_DAYS günde kapanır.
     *
     * @return array{reminded: int, expired: int}
     */
    public function remindAndExpire(): array
    {
        $result = ['reminded' => 0, 'expired' => 0];
        foreach (StoreReturn::with('order')->where('status', 'approved')->where('created_at', '<', now()->subDays(StoreReturn::REMIND_DAYS))->get() as $return) {
            if ($return->created_at->lt(now()->subDays(StoreReturn::EXPIRE_DAYS))) {
                $this->transition($return, 'approved', 'expired', ['closed_at' => now()], 'store.return_expired');
                $this->mailer->returnUpdate($return->order, $return, 'İade talebiniz kapandı',
                    "{$return->return_no} numaralı iade talebiniz, ".StoreReturn::EXPIRE_DAYS.' gün içinde kargo bilgisi girilmediği için kapandı. Ürünü kargoya verdiyseniz bize ulaşın; iadenizi tamamlarız.');
                $result['expired']++;
            } elseif ($return->reminded_at === null) {
                $return->forceFill(['reminded_at' => now()])->save();
                $this->mailer->returnUpdate($return->order, $return, 'İade kargonuzu bekliyoruz',
                    "{$return->return_no} numaralı iade talebiniz onaylandı ancak henüz kargo bilgisi girilmedi. ".self::shippingInstructions($return));
                $result['reminded']++;
            }
        }

        return $result;
    }

    /** Bu iadeyle siparişteki tüm ürünler iade edilmiş olur mu? (Reddedilenler hariç tüm talepler.) */
    private function completesOrder(StoreReturn $return): bool
    {
        return array_sum($this->returnableQuantities($this->order($return))) === 0;
    }

    /** @throws DomainException */
    /** @param  string|list<string>  $from */
    private function transition(StoreReturn $return, string|array $from, string $to, array $extra, string $auditAction): void
    {
        DB::transaction(function () use ($return, $from, $to, $extra, $auditAction) {
            $locked = StoreReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, (array) $from, true)) {
                throw new DomainException('İade talebi bu durumdayken bu işlem yapılamaz ('.$locked->statusLabel().').');
            }
            $old = $locked->status;
            $locked->forceFill(['status' => $to] + $extra)->save();
            $return->setRawAttributes($locked->getAttributes(), true);
            $order = $this->order($return);
            $this->event($order, "İade {$return->return_no}: ".StoreReturn::STATUSES[$to].'.');
            $this->audit->log($auditAction, $return, ['status' => $old], ['status' => $to, 'order_no' => $order->order_no, 'return_no' => $return->return_no]);
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
