<?php

namespace App\Domain\Ordering;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\DocumentUploader;
use App\Domain\Inventory\Quantity;
use App\Domain\Support\LocalTime;
use App\Domain\Tenancy\TenantContext;
use App\Models\BusinessCounter;
use App\Models\Order;
use App\Models\OrderReturnRequest;
use App\Models\OrderReturnRequestItem;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Bayinin iade talebi (docs/03-is-kurallari.md §3.7):
 *  - Bayi tamamlanmış siparişten kalem / miktar / neden / fotoğrafla talep açar. Miktar; satılan − iade edilen − açık
 *    taleplerdeki miktarı aşamaz. Talep değerlendirilmeden önce bayi geri çekebilir.
 *  - Toptancı onaylar (gönderim bilgisi yazar) veya gerekçeyle reddeder.
 *  - Ürünler gelince toptancı teslim alır: gelen ve tekrar satılabilir miktarı girer. Mevcut iade kaydı (ReturnService)
 *    oluşur: bayinin carisine alacak, yalnızca tekrar satılabilir miktar için stok girişi. Her kayıt bir kez işlenir.
 */
class ReturnRequestService
{
    public function __construct(
        private ReturnService $returns,
        private OrderService $orders,
        private DocumentUploader $documents,
        private AuditLogger $audit,
        private TenantContext $tenant,
    ) {}

    /** @return array<int, string> kalem ID'si → talep edilebilir kalan miktar */
    public static function requestable(Order $order): array
    {
        $open = OrderReturnRequestItem::whereIn('order_item_id', $order->items->modelKeys())
            ->whereHas('request', fn ($q) => $q->whereIn('status', OrderReturnRequest::OPEN))
            ->selectRaw('order_item_id, SUM(quantity) q')->groupBy('order_item_id')->pluck('q', 'order_item_id');

        return collect(ReturnService::returnable($order))
            ->map(fn ($qty, $itemId) => bcsub($qty, (string) ($open[$itemId] ?? '0'), 3))->all();
    }

    /**
     * @param  array<int, string>  $quantities  kalem ID'si → iade miktarı
     * @param  UploadedFile[]  $photos
     *
     * @throws DomainException
     */
    public function request(Order $order, int $relationshipId, array $quantities, string $reason, ?string $note, array $photos, int $dealerUserId): OrderReturnRequest
    {
        if (! isset(OrderReturnRequest::REASONS[$reason])) {
            throw new DomainException('İade nedenini seçin.');
        }
        $photos = array_values(array_filter($photos, fn ($f) => $f instanceof UploadedFile));
        if (count($photos) > OrderReturnRequest::MAX_PHOTOS) {
            throw new DomainException('En fazla '.OrderReturnRequest::MAX_PHOTOS.' fotoğraf ekleyebilirsiniz.');
        }
        if ($photos === [] && in_array($reason, OrderReturnRequest::PHOTO_REASONS, true)) {
            throw new DomainException('Bu iade nedeni için ürünün en az bir fotoğrafını ekleyin.');
        }
        $note = trim((string) $note) ?: null;
        if ($reason === 'other' && $note === null) {
            throw new DomainException('"Diğer" seçtiyseniz iade nedenini açıklamaya yazın.');
        }

        $stored = [];
        try {
            return DB::transaction(function () use ($order, $relationshipId, $quantities, $reason, $note, $photos, $dealerUserId, &$stored) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($order->dealer_relationship_id !== $relationshipId) {
                    throw new DomainException('Sipariş bulunamadı.');
                }
                if (! in_array($order->status, Order::RETURNABLE, true)) {
                    throw new DomainException('İade talebi yola çıkmış veya teslim edilmiş siparişler için açılır. Henüz yola çıkmamış siparişi "Siparişi iptal et" ile iptal edebilirsiniz.');
                }

                $order->load('items.product.unit');
                $requestable = self::requestable($order);
                $lines = [];
                foreach ($order->items as $item) {
                    $raw = trim((string) ($quantities[$item->id] ?? ''));
                    if ($raw === '' || $raw === '0') {
                        continue;
                    }
                    $qty = Quantity::parse($raw);
                    if ($qty === null || bccomp($qty, '0', 3) <= 0) {
                        throw new DomainException("\"{$item->name}\" için geçerli bir iade miktarı girin.");
                    }
                    if (bccomp($qty, $requestable[$item->id] ?? '0', 3) > 0) {
                        throw new DomainException("\"{$item->name}\" için en fazla ".Quantity::format($requestable[$item->id] ?? '0').' iade talep edilebilir.');
                    }
                    if (! $item->allowsFraction() && ! Quantity::isWhole($qty)) {
                        throw new DomainException("\"{$item->name}\" için küsuratlı miktar girilemez.");
                    }
                    $lines[] = ['order_item_id' => $item->id, 'quantity' => $qty];
                }
                if ($lines === []) {
                    throw new DomainException('İade etmek istediğiniz en az bir ürünün miktarını girin.');
                }

                $request = OrderReturnRequest::create([
                    'order_id' => $order->id, 'dealer_relationship_id' => $relationshipId, 'request_no' => $this->nextNo(),
                    'status' => 'requested', 'reason' => $reason, 'dealer_note' => $note,
                ]);
                foreach ($lines as $line) {
                    $request->items()->create($line);
                }
                foreach ($photos as $photo) {
                    $attachment = $this->documents->store($photo, "returns/{$request->id}", 'order_return_request', $request->id, 'dealer', $dealerUserId,
                        ['image/jpeg' => 'jpg', 'image/png' => 'png']);
                    $stored[] = $attachment->stored_path;
                }

                $this->audit->log('order.return_requested', $request, [], ['order_id' => $order->id, 'request_no' => $request->request_no, 'reason' => $reason]);

                return $request;
            });
        } catch (\Throwable $e) {
            // İşlem geri alındıysa yüklenen fotoğraflar da silinir.
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    /** Bayi, değerlendirilmemiş talebini geri çeker. */
    public function cancelByDealer(OrderReturnRequest $request, int $relationshipId): OrderReturnRequest
    {
        return $this->transition($request, ['requested'], 'cancelled', ['cancelled_at' => now()], 'order.return_request_cancelled', $relationshipId);
    }

    public function approve(OrderReturnRequest $request, ?string $note, int $userId): OrderReturnRequest
    {
        return $this->transition($request, ['requested'], 'approved',
            ['approved_at' => now(), 'business_note' => trim((string) $note) ?: null, 'decided_by' => $userId], 'order.return_request_approved');
    }

    public function reject(OrderReturnRequest $request, string $reason, int $userId): OrderReturnRequest
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Ret gerekçesini yazın; bayiye gösterilir.');
        }

        return $this->transition($request, ['requested', 'approved'], 'rejected',
            ['rejected_at' => now(), 'reject_reason' => mb_substr($reason, 0, 1000), 'decided_by' => $userId], 'order.return_request_rejected');
    }

    /**
     * Ürünler geldi: gelen miktar cariye alacak yazılır, tekrar satılabilir miktar depoya girer.
     *
     * @param  array<int, string>  $received  talep kalemi ID'si → teslim alınan miktar
     * @param  array<int, string>  $resellable  talep kalemi ID'si → tekrar satılabilir miktar
     */
    public function receive(OrderReturnRequest $request, array $received, array $resellable, ?string $note, int $userId): OrderReturnRequest
    {
        return DB::transaction(function () use ($request, $received, $resellable, $note, $userId) {
            // Kilit sırası: sipariş, sonra talep (ReturnService de siparişi kilitler).
            $order = Order::whereKey($request->order_id)->lockForUpdate()->firstOrFail();
            $request = OrderReturnRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! in_array($request->status, ['requested', 'approved'], true)) {
                throw new DomainException('Bu iade talebi zaten sonuçlandırılmış.');
            }

            $quantities = [];
            $restock = [];
            foreach ($request->items()->with('orderItem')->get() as $item) {
                $name = $item->orderItem->name;
                $got = Quantity::parse(trim((string) ($received[$item->id] ?? '0')) ?: '0');
                $good = Quantity::parse(trim((string) ($resellable[$item->id] ?? '0')) ?: '0');
                if ($got === null || bccomp($got, '0', 3) < 0 || bccomp($got, (string) $item->quantity, 3) > 0) {
                    throw new DomainException("\"{$name}\" için teslim alınan miktar 0 ile talep edilen miktar arasında olmalıdır.");
                }
                if ($good === null || bccomp($good, '0', 3) < 0 || bccomp($good, $got, 3) > 0) {
                    throw new DomainException("\"{$name}\" için tekrar satılabilir miktar 0 ile teslim alınan miktar arasında olmalıdır.");
                }
                if (bccomp($got, '0', 3) > 0) {
                    $quantities[$item->order_item_id] = $got;
                    $restock[$item->order_item_id] = $good;
                }
            }
            if ($quantities === []) {
                throw new DomainException('Hiç ürün teslim alınmadıysa talebi reddedin.');
            }

            $note = trim((string) $note) ?: null;
            // Yoldaki sipariş geri gönderildiyse önce teslim edilmiş sayılır (iade kaydı tamamlanmış siparişe yazılır).
            if ($order->status === 'shipped') {
                $this->orders->completeForReturn($order, $request->request_no);
            }
            $return = $this->returns->create($order, $quantities, mb_substr("İade talebi {$request->request_no}".($note ? " · {$note}" : ''), 0, 500), $userId, $restock);
            $request->update(['status' => 'received', 'received_at' => now(), 'order_return_id' => $return->id, 'decided_by' => $userId]
                + ($note ? ['business_note' => mb_substr(trim($request->business_note."\n".$note), 0, 1000)] : []));
            $this->audit->log('order.return_request_received', $request, [], ['order_return_id' => $return->id, 'grand_total' => (string) $return->grand_total]);

            return $request->setRelation('orderReturn', $return);
        });
    }

    private function transition(OrderReturnRequest $request, array $from, string $to, array $values, string $event, ?int $relationshipId = null): OrderReturnRequest
    {
        return DB::transaction(function () use ($request, $from, $to, $values, $event, $relationshipId) {
            $request = OrderReturnRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($relationshipId !== null && $request->dealer_relationship_id !== $relationshipId) {
                throw new DomainException('İade talebi bulunamadı.');
            }
            if (! in_array($request->status, $from, true)) {
                throw new DomainException('İade talebi bu durumdayken bu işlem yapılamaz.');
            }
            $old = $request->status;
            $request->update(['status' => $to] + $values);
            $this->audit->log($event, $request, ['status' => $old], ['status' => $to]);

            return $request;
        });
    }

    /** IADE-2026-000001 biçiminde, işletme ve yıl bazında kesintisiz sıra. */
    private function nextNo(): string
    {
        $year = LocalTime::now()->year;
        $key = "return_request:{$year}";
        DB::statement('INSERT INTO business_counters (business_id, `key`, value) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE id = id', [$this->tenant->businessId(), $key]);
        $counter = BusinessCounter::where('key', $key)->lockForUpdate()->firstOrFail();
        $counter->increment('value');

        return sprintf('IADE-%d-%06d', $year, $counter->value);
    }
}
