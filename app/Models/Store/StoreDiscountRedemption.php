<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kupon / kampanya kullanımı. İptal edilen veya ödenmeyen siparişte geçersiz (void) olur ve kullanım hakkı geri döner. */
class StoreDiscountRedemption extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(StoreDiscount::class, 'discount_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }
}
