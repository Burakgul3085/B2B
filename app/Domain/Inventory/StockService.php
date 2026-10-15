<?php

namespace App\Domain\Inventory;

use App\Domain\Auth\CurrentActor;
use App\Domain\Ordering\ProductRequestService;
use App\Domain\Tenancy\TenantContext;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stoğu değiştiren TEK yer. Kurallar (docs/03-is-kurallari.md §2):
 *  S1 anlık stok = hareket toplamı (aynı transaction),
 *  S2 negatif stok yok (satır kilidi + CHECK),
 *  S3 hareketler silinmez,
 *  S6 birden çok satır product_id artan sırada kilitlenir,
 *  source_key UNIQUE ile aynı işlem ikinci kez uygulanamaz.
 * Rezervasyon yoktur.
 */
class StockService
{
    /** Kullanıcının elle yapabildiği tek ürün/tek depo hareketleri ve yönleri. */
    public const MANUAL_TYPES = [
        'opening' => 1,
        'increase' => 1,
        'decrease' => -1,
        'return_in' => 1,
        'manual_adjust' => 0, // işaretli miktar
    ];

    public function __construct(private TenantContext $tenant, private CurrentActor $actor) {}

    /**
     * Tek bir elle stok hareketi uygular.
     *
     * @param  string  $qty  Pozitif miktar; manual_adjust için işaretli (+/-) miktar.
     */
    public function adjust(Product $product, Warehouse $warehouse, string $type, string $qty, ?string $note = null, ?string $sourceKey = null): StockMovement
    {
        if (! array_key_exists($type, self::MANUAL_TYPES)) {
            throw new DomainException('Geçersiz stok hareketi türü.');
        }

        $direction = self::MANUAL_TYPES[$type];

        if ($direction === 0) {
            if (bccomp($qty, '0', Quantity::SCALE) === 0) {
                throw new DomainException('Düzeltme miktarı sıfır olamaz.');
            }
            Quantity::assertValidFor($product, ltrim($qty, '-'));
            if ($note === null || trim($note) === '') {
                throw new DomainException('Manuel düzeltme için açıklama zorunludur.');
            }
            $signed = $qty;
        } else {
            Quantity::assertValidFor($product, $qty);
            $signed = $direction < 0 ? Quantity::negate($qty) : $qty;
        }

        if ($type === 'opening' && StockMovement::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->exists()) {
            throw new DomainException('Bu ürün için bu depoda daha önce stok hareketi var; ilk stok girişi yerine "Stok girişi" kullanın.');
        }

        $this->assertWarehouseUsable($warehouse);

        return DB::transaction(fn () => $this->apply($product, $warehouse->id, $type, $signed, $note, $sourceKey));
    }

    /** Depolar arası transfer: iki hareket tek transaction'da; kaynak yetersizse hiçbiri yazılmaz. */
    public function transfer(Product $product, Warehouse $from, Warehouse $to, string $qty, ?string $note = null): string
    {
        if ($from->id === $to->id) {
            throw new DomainException('Kaynak ve hedef depo aynı olamaz.');
        }
        Quantity::assertValidFor($product, $qty);
        $this->assertWarehouseUsable($from);
        $this->assertWarehouseUsable($to);

        $uuid = (string) Str::uuid();

        DB::transaction(function () use ($product, $from, $to, $qty, $note, $uuid) {
            // Sabit kilit sırası: önce küçük depo ID'si (kilitlenme önleme).
            foreach (collect([$from->id, $to->id])->sort() as $warehouseId) {
                $this->lockRow($warehouseId, $product->id);
            }

            $this->apply($product, $from->id, 'transfer_out', Quantity::negate($qty), $note, "transfer:{$uuid}:out", ['transfer_uuid' => $uuid]);
            $this->apply($product, $to->id, 'transfer_in', $qty, $note, "transfer:{$uuid}:in", ['transfer_uuid' => $uuid]);
        });

        return $uuid;
    }

    /**
     * Sayımı uygular. Fark, sayım girildiği andaki değil UYGULAMA anındaki stoka göre hesaplanır (S5).
     */
    public function applyCount(StockCount $count): StockCount
    {
        return DB::transaction(function () use ($count) {
            $count = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            if ($count->status !== 'draft') {
                throw new DomainException('Bu sayım zaten uygulanmış veya iptal edilmiş.');
            }

            $warehouse = Warehouse::findOrFail($count->warehouse_id);
            $this->assertWarehouseUsable($warehouse);

            $lines = $count->lines()->with('product.unit')->orderBy('product_id')->get();
            if ($lines->isEmpty()) {
                throw new DomainException('Sayımda en az bir ürün olmalıdır.');
            }

            foreach ($lines as $line) {
                $current = $this->lockRow($warehouse->id, $line->product_id);
                $difference = bcsub((string) $line->counted_qty, $current, Quantity::SCALE);

                $line->update(['system_qty' => $current, 'difference' => $difference]);

                if (bccomp($difference, '0', Quantity::SCALE) !== 0) {
                    $this->apply($line->product, $warehouse->id, 'count_adjust', $difference,
                        "Sayım #{$count->id}", "count:{$count->id}:{$line->product_id}",
                        ['reference_type' => 'stock_count', 'reference_id' => $count->id]);
                }
            }

            $count->update(['status' => 'applied', 'applied_at' => now()]);

            return $count;
        });
    }

    /**
     * Birden çok kalemi tek transaction içinde uygular (sipariş onayı/iptali bunu kullanacak).
     * Çağıran transaction açmış olmalıdır. Kalemler product_id artan sırada kilitlenir.
     *
     * @param  array<int, array{product: Product, qty: string, source_key: string}>  $lines  qty işaretli
     */
    public function applyMany(int $warehouseId, string $type, array $lines, array $reference = []): void
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('applyMany bir veritabanı transaction içinde çağrılmalıdır.');
        }

        usort($lines, fn ($a, $b) => $a['product']->id <=> $b['product']->id);

        // Önce tüm satırları kilitle ve yeterliliği kontrol et; biri yetersizse hiçbir hareket yazılmaz.
        foreach ($lines as $line) {
            $current = $this->lockRow($warehouseId, $line['product']->id);
            if (bccomp(bcadd($current, $line['qty'], Quantity::SCALE), '0', Quantity::SCALE) < 0) {
                throw new InsufficientStock($line['product']->id, $line['product']->name, $current, ltrim($line['qty'], '-'));
            }
        }

        foreach ($lines as $line) {
            $this->apply($line['product'], $warehouseId, $type, $line['qty'], null, $line['source_key'], $reference);
        }
    }

    /** Ürünün depo bazında stoğu (yalnızca okuma, kilitsiz). */
    public function available(int $productId, int $warehouseId): string
    {
        $qty = WarehouseStock::where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity');

        return bcadd((string) ($qty ?? '0'), '0', Quantity::SCALE);
    }

    /**
     * Hareketi yazar ve anlık stoğu günceller. Satırı kilitler (zaten kilitliyse aynı transaction'da sorun yok).
     */
    private function apply(Product $product, int $warehouseId, string $type, string $signedQty, ?string $note, ?string $sourceKey, array $extra = []): StockMovement
    {
        $current = $this->lockRow($warehouseId, $product->id);
        $new = bcadd($current, $signedQty, Quantity::SCALE);

        if (bccomp($new, '0', Quantity::SCALE) < 0) {
            throw new InsufficientStock($product->id, $product->name, $current, ltrim($signedQty, '-'));
        }

        WarehouseStock::where('warehouse_id', $warehouseId)->where('product_id', $product->id)
            ->update(['quantity' => $new, 'updated_at' => now()]);

        $actor = $this->actor->get();

        // Sevk deposuna stok girdiyse, commit sonrasında "gelince haber ver" talepleri işaretlenir.
        if (bccomp($signedQty, '0', Quantity::SCALE) > 0) {
            $productId = $product->id;
            $isSalesWarehouse = (int) BusinessSetting::where('business_id', $this->tenant->businessId())->value('default_warehouse_id') === $warehouseId;
            if ($isSalesWarehouse) {
                DB::afterCommit(fn () => app(ProductRequestService::class)->onStockIncreased($productId));
            }
        }

        return StockMovement::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $signedQty,
            'balance_after' => $new,
            'note' => $note !== null ? Str::limit(trim($note), 500, '') : null,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'source_key' => $sourceKey,
        ] + $extra);
    }

    /** Stok satırını (yoksa oluşturup) FOR UPDATE ile kilitler ve güncel miktarı döndürür. */
    private function lockRow(int $warehouseId, int $productId): string
    {
        $businessId = $this->tenant->businessId();

        DB::statement(
            'INSERT INTO warehouse_stock (business_id, warehouse_id, product_id, quantity, created_at, updated_at)
             VALUES (?, ?, ?, 0, NOW(), NOW()) ON DUPLICATE KEY UPDATE id = id',
            [$businessId, $warehouseId, $productId]
        );

        $qty = WarehouseStock::where('warehouse_id', $warehouseId)->where('product_id', $productId)
            ->lockForUpdate()->value('quantity');

        return bcadd((string) $qty, '0', Quantity::SCALE);
    }

    private function assertWarehouseUsable(Warehouse $warehouse): void
    {
        if (! $warehouse->is_active) {
            throw new DomainException("\"{$warehouse->name}\" deposu pasif; stok hareketi yapılamaz.");
        }
    }
}
