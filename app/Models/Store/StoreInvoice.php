<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sipariş faturası (numara, tarih, PDF). Dosya yerel diskte, herkese açık olmayan klasörde durur. */
class StoreInvoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issued_on' => 'date'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }
}
