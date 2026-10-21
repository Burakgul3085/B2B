<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** İade talebindeki kalem; teslim alınırken sağlam bulunan adet stoğa döner. */
class StoreReturnItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'restocked_quantity' => 'integer'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(StoreOrderItem::class, 'order_item_id');
    }
}
