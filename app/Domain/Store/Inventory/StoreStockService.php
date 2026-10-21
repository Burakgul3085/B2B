<?php

namespace App\Domain\Store\Inventory;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreStockHold;
use App\Models\Store\StoreStockMovement;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mağaza stoğunu değiştiren TEK yer (docs/13-magaza-mimarisi.md §8.2).
 *  - Varyant stoğu = hareket toplamı; ikisi aynı transaction'da, varyant satırı kilitlenerek güncellenir.
 *  - Stok eksiye düşmez (kilit + CHECK). Hareketler silinmez; elle yapılanlar ters kayıtla geri alınır.
 *  - Ayırma: ödeme sırasında stok bir süreliğine ayrılır; satılabilir = stok − etkin ayırmalar.
 *    Elle azaltma, ayrılmış miktarın altına inemez.
 *  - Aynı source_key ile ikinci hareket yazılamaz (aynı işlem iki kez uygulanmaz).
 *  - Birden çok varyant kilitlenirken kimlik sırası izlenir (kilitlenme önlenir).
 */
class StoreStockService
{
    public const MAX_QUANTITY = 1_000_000;

    public function __construct(private AuditLogger $audit, private CurrentActor $actor) {}

    /* ---------------- Elle yapılan hareketler ---------------- */

    /** Mal girişi. @throws DomainException */
    public function receive(StoreProductVariant $variant, int $quantity, ?string $note = null): StoreStockMovement
    {
        $this->assertQuantity($quantity);

        return DB::transaction(function () use ($variant, $quantity, $note) {
            $movement = $this->apply($variant->id, 'receipt', $quantity, $note);
            $this->audit->log('store.stock_received', $movement->variant, [], ['quantity' => $quantity, 'balance' => $movement->balance_after]);

            return $movement;
        });
    }

    /**
     * Sayım: gerçek miktar girilir, fark hareket olarak yazılır. Fark yoksa hareket oluşmaz.
     *
     * @throws DomainException
     */
    public function count(StoreProductVariant $variant, int $actual, ?string $note = null): ?StoreStockMovement
    {
        if ($actual < 0 || $actual > self::MAX_QUANTITY) {
            throw new DomainException('Sayılan miktar 0 veya daha büyük bir tam sayı olmalıdır.');
        }

        return DB::transaction(function () use ($variant, $actual, $note) {
            $current = $this->lock([$variant->id])->first();
            $diff = $actual - $current->stock_quantity;
            if ($diff === 0) {
                return null;
            }

            $movement = $this->apply($variant->id, 'count', $diff, $note ?? 'Sayım', locked: $current);
            $this->audit->log('store.stock_counted', $current, ['stock' => $current->stock_quantity - $diff], ['stock' => $actual]);

            return $movement;
        });
    }

    /**
     * Gerekçeli düzeltme (fire, hasar, kayıp…): işaretli miktar.
     *
     * @throws DomainException
     */
    public function adjust(StoreProductVariant $variant, int $signed, string $note): StoreStockMovement
    {
        if ($signed === 0) {
            throw new DomainException('Düzeltme miktarı sıfır olamaz.');
        }
        $this->assertQuantity(abs($signed));
        if (trim($note) === '') {
            throw new DomainException('Düzeltme için açıklama zorunludur.');
        }

        return DB::transaction(function () use ($variant, $signed, $note) {
            $movement = $this->apply($variant->id, 'adjustment', $signed, trim($note));
            $this->audit->log('store.stock_adjusted', $movement->variant, [], ['quantity' => $signed, 'note' => trim($note)]);

            return $movement;
        });
    }

    /**
     * Elle yapılmış bir hareketi ters kayıtla geri alır. Her hareket yalnızca bir kez geri alınabilir.
     *
     * @throws DomainException
     */
    public function reverse(StoreStockMovement $movement, ?string $note = null): StoreStockMovement
    {
        if (! in_array($movement->type, StoreStockMovement::REVERSIBLE, true)) {
            throw new DomainException('Bu hareket elle geri alınamaz; satış ve iadeler sipariş üzerinden düzeltilir.');
        }

        return DB::transaction(function () use ($movement, $note) {
            if (StoreStockMovement::where('reverses_id', $movement->id)->lockForUpdate()->exists()) {
                throw new DomainException('Bu hareket zaten geri alınmış.');
            }

            try {
                $reversal = $this->apply($movement->variant_id, 'reversal', -$movement->quantity,
                    trim($note ?? '') ?: "#{$movement->id} numaralı hareketin ters kaydı", reversesId: $movement->id);
            } catch (QueryException $e) {
                throw new DomainException('Bu hareket zaten geri alınmış.', previous: $e);
            }
            $this->audit->log('store.stock_reversed', $reversal->variant, [], ['movement_id' => $movement->id, 'quantity' => $reversal->quantity]);

            return $reversal;
        });
    }

    /* ---------------- Ayırma (ödeme sırasında) ---------------- */

    /**
     * Sepetteki ürünleri bir süreliğine ayırır. Aynı sahip için önceki ayırmaların yerini alır (sepet değişebilir).
     *
     * @param  array<int, int>  $quantities  varyant id => adet
     *
     * @throws InsufficientStoreStock
     */
    public function hold(string $holder, array $quantities, int $minutes = 15): void
    {
        $quantities = array_filter($quantities, fn ($q) => $q > 0);

        DB::transaction(function () use ($holder, $quantities, $minutes) {
            $variants = $this->lock(array_keys($quantities))->keyBy('id');
            $held = $this->activeHeld(array_keys($quantities), exceptHolder: $holder);
            $shortages = [];

            foreach ($quantities as $id => $qty) {
                $variant = $variants->get($id) ?? throw new DomainException('Ürün bulunamadı.');
                $available = $variant->stock_quantity - ($held[$id] ?? 0);
                if ($qty > $available) {
                    $shortages[$id] = max(0, $available);
                }
            }
            if ($shortages) {
                throw new InsufficientStoreStock($shortages);
            }

            StoreStockHold::where('holder', $holder)->whereNull('converted_at')->delete();
            foreach ($quantities as $id => $qty) {
                StoreStockHold::create(['variant_id' => $id, 'holder' => $holder, 'quantity' => $qty, 'expires_at' => now()->addMinutes($minutes)]);
            }
        });
    }

    /** Ayırmayı bırakır (ödeme başarısız, sepet terk edildi). */
    public function release(string $holder): void
    {
        StoreStockHold::where('holder', $holder)->whereNull('released_at')->whereNull('converted_at')->update(['released_at' => now()]);
    }

    /**
     * Ayırmayı satışa çevirir: her varyant için satış hareketi yazılır. Süresi dolmuş ayırma, stok hâlâ yetiyorsa
     * çevrilir. Aynı sahip için ikinci çağrı yeni hareket yazmaz.
     *
     * @return Collection<int, StoreStockMovement>
     *
     * @throws InsufficientStoreStock
     */
    public function convert(string $holder, string $referenceType, int $referenceId): Collection
    {
        return DB::transaction(function () use ($holder, $referenceType, $referenceId) {
            $holds = StoreStockHold::where('holder', $holder)->whereNull('released_at')->lockForUpdate()->get()->keyBy('variant_id');
            if ($holds->isEmpty()) {
                throw new DomainException('Ayrılmış ürün bulunamadı; süre dolmuş olabilir.');
            }
            if ($holds->every(fn ($h) => $h->converted_at !== null)) {
                return StoreStockMovement::where('source_key', 'like', $holder.':sale:%')->get();
            }

            $variants = $this->lock($holds->keys()->all())->keyBy('id');
            $held = $this->activeHeld($holds->keys()->all(), exceptHolder: $holder);
            $shortages = [];
            foreach ($holds as $id => $hold) {
                $available = $variants[$id]->stock_quantity - ($held[$id] ?? 0);
                if ($hold->quantity > $available) {
                    $shortages[$id] = max(0, $available);
                }
            }
            if ($shortages) {
                throw new InsufficientStoreStock($shortages);
            }

            $movements = collect();
            foreach ($holds as $id => $hold) {
                $movements->push($this->apply($id, 'sale', -$hold->quantity, null, "{$holder}:sale:{$id}", $referenceType, $referenceId, locked: $variants[$id]));
                $hold->forceFill(['converted_at' => now()])->save();
            }

            return $movements;
        });
    }

    /**
     * Ayırma olmadan satış (ör. kapıda ödeme siparişi oluşurken). Satılabilir miktar yetmezse hiçbiri yazılmaz.
     *
     * @param  array<int, int>  $quantities  varyant id => adet
     * @return Collection<int, StoreStockMovement>
     *
     * @throws InsufficientStoreStock
     */
    public function sell(array $quantities, string $sourceKey, string $referenceType, int $referenceId): Collection
    {
        return DB::transaction(function () use ($quantities, $sourceKey, $referenceType, $referenceId) {
            $ids = array_keys($quantities);
            $variants = $this->lock($ids)->keyBy('id');
            $held = $this->activeHeld($ids);
            $shortages = [];
            foreach ($quantities as $id => $qty) {
                $available = ($variants[$id]->stock_quantity ?? 0) - ($held[$id] ?? 0);
                if ($qty > $available) {
                    $shortages[$id] = max(0, $available);
                }
            }
            if ($shortages) {
                throw new InsufficientStoreStock($shortages);
            }

            return collect($quantities)->map(fn ($qty, $id) => $this->apply($id, 'sale', -$qty, null, "{$sourceKey}:sale:{$id}", $referenceType, $referenceId, locked: $variants[$id]))->values();
        });
    }

    /** İade/iptal ile stoğa geri giriş. Aynı source_key ikinci kez uygulanmaz. */
    public function restock(int $variantId, int $quantity, string $sourceKey, string $referenceType, int $referenceId, ?string $note = null): StoreStockMovement
    {
        $this->assertQuantity($quantity);

        return DB::transaction(fn () => StoreStockMovement::where('source_key', $sourceKey)->first()
            ?? $this->apply($variantId, 'return', $quantity, $note, $sourceKey, $referenceType, $referenceId));
    }

    /* ---------------- Sorgular ---------------- */

    /**
     * Satılabilir miktarlar: stok − etkin ayırmalar.
     *
     * @param  list<int>  $variantIds
     * @return array<int, int>
     */
    public function available(array $variantIds): array
    {
        $stock = StoreProductVariant::whereIn('id', $variantIds)->pluck('stock_quantity', 'id');
        $held = $this->activeHeld($variantIds);

        return $stock->map(fn ($qty, $id) => max(0, $qty - ($held[$id] ?? 0)))->all();
    }

    /**
     * @param  list<int>  $variantIds
     * @return array<int, int> varyant id => etkin ayrılmış adet
     */
    public function activeHeld(array $variantIds, ?string $exceptHolder = null): array
    {
        if ($variantIds === []) {
            return [];
        }

        return StoreStockHold::active()->whereIn('variant_id', $variantIds)
            ->when($exceptHolder, fn ($q) => $q->where('holder', '!=', $exceptHolder))
            ->groupBy('variant_id')->selectRaw('variant_id, SUM(quantity) as qty')
            ->pluck('qty', 'variant_id')->map(fn ($q) => (int) $q)->all();
    }

    /* ---------------- İç işleyiş ---------------- */

    /**
     * Tek hareket: varyant satırı kilitlenir, yeni stok hesaplanır, hareket ve önbellek birlikte yazılır.
     * Çağıran transaction içinde olmalıdır.
     *
     * @throws DomainException
     */
    private function apply(
        int $variantId,
        string $type,
        int $quantity,
        ?string $note = null,
        ?string $sourceKey = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $reversesId = null,
        ?StoreProductVariant $locked = null,
    ): StoreStockMovement {
        if ($sourceKey !== null && StoreStockMovement::where('source_key', $sourceKey)->exists()) {
            throw new DomainException('Bu stok işlemi daha önce uygulanmış.');
        }

        $variant = $locked ?? $this->lock([$variantId])->first() ?? throw new DomainException('Ürün bulunamadı.');
        $balance = $variant->stock_quantity + $quantity;

        if ($balance < 0) {
            if (in_array($type, ['count', 'adjustment', 'reversal'], true)) {
                throw new DomainException("Stokta {$variant->stock_quantity} adet var; bu işlem stoğu eksiye düşürür.");
            }
            throw new InsufficientStoreStock([$variantId => $variant->stock_quantity]);
        }
        // Elle azaltma, ödeme aşamasındaki müşterilere ayrılmış miktarın altına inemez.
        if ($quantity < 0 && in_array($type, ['count', 'adjustment', 'reversal'], true)) {
            $held = $this->activeHeld([$variantId])[$variantId] ?? 0;
            if ($balance < $held) {
                throw new DomainException("Bu üründen {$held} adet ödeme aşamasındaki müşteriler için ayrılmış; stok bunun altına indirilemez.");
            }
        }
        if ($balance > self::MAX_QUANTITY) {
            throw new DomainException('Stok miktarı çok büyük.');
        }

        $actor = $this->actor->get();
        $movement = StoreStockMovement::create([
            'variant_id' => $variantId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'note' => $note !== null ? mb_substr($note, 0, 500) : null,
            'source_key' => $sourceKey,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reverses_id' => $reversesId,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
        ]);

        $variant->stock_quantity = $balance;
        $variant->save();
        $movement->setRelation('variant', $variant);

        return $movement;
    }

    /**
     * Varyant satırlarını kimlik sırasıyla kilitler.
     *
     * @param  list<int>  $ids
     * @return Collection<int, StoreProductVariant>
     */
    private function lock(array $ids): Collection
    {
        sort($ids);

        return StoreProductVariant::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    /** @throws DomainException */
    private function assertQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new DomainException('Miktar 1 ile '.number_format(self::MAX_QUANTITY, 0, ',', '.').' arasında bir tam sayı olmalıdır.');
        }
    }
}
