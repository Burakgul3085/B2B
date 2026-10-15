<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Cari hareketi: yalnızca eklenir. Düzeltme ters kayıtla yapılır. */
class CurrentAccountMovement extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    public const TYPES = [
        'opening' => 'Açılış bakiyesi',
        'order_debit' => 'Sipariş',
        'collection' => 'Tahsilat',
        'return_credit' => 'İade',
        'adjustment_debit' => 'Borç düzeltmesi',
        'adjustment_credit' => 'Alacak düzeltmesi',
        'reversal' => 'Ters kayıt',
    ];

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'movement_date' => 'date', 'due_date' => 'date', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cari hareket değiştirilemez.'));
        static::deleting(fn () => throw new LogicException('Cari hareket silinemez.'));
    }
}
