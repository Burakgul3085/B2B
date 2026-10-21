<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ödeme denemesi (başarısızlar dahil). Kart numarası saklanmaz; yalnızca kart ailesi ve son dört hane. */
class StorePayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['raw' => 'array', 'installment' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }
}
