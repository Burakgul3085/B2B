<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Stok hareketi: yalnızca eklenir. Yanlış hareket ters yönlü yeni hareketle düzeltilir. */
class StockMovement extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    public const TYPES = [
        'opening' => 'İlk stok girişi',
        'increase' => 'Stok girişi',
        'decrease' => 'Stok çıkışı',
        'sale' => 'Satış (sipariş onayı)',
        'sale_reversal' => 'Satış iptali',
        'return_in' => 'İade girişi',
        'transfer_out' => 'Transfer çıkışı',
        'transfer_in' => 'Transfer girişi',
        'count_adjust' => 'Sayım farkı',
        'manual_adjust' => 'Manuel düzeltme',
    ];

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'balance_after' => 'decimal:3', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stok hareketi değiştirilemez.'));
        static::deleting(fn () => throw new LogicException('Stok hareketi silinemez.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
