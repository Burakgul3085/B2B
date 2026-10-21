<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Mağaza stok hareketi. Yalnızca eklenir: güncellenemez, silinemez; düzeltme ters kayıtla yapılır. */
class StoreStockMovement extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'receipt' => 'Stok girişi',
        'count' => 'Sayım farkı',
        'adjustment' => 'Düzeltme',
        'sale' => 'Satış',
        'return' => 'İade',
        'reversal' => 'Ters kayıt',
    ];

    /** Elle yapılan ve ters kayıtla geri alınabilen hareketler. Satış ve iade sipariş üzerinden düzeltilir. */
    public const REVERSIBLE = ['receipt', 'count', 'adjustment'];

    protected $fillable = ['variant_id', 'type', 'quantity', 'balance_after', 'note', 'source_key', 'reference_type', 'reference_id', 'reverses_id', 'actor_type', 'actor_id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'balance_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stok hareketleri değiştirilemez; ters kayıt kullanın.'));
        static::deleting(fn () => throw new LogicException('Stok hareketleri silinemez; ters kayıt kullanın.'));
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreProductVariant::class, 'variant_id');
    }

    /** Bu hareketi geri alan ters kayıt (varsa). */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
