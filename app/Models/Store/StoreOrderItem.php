<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sipariş kalemi: ad, seçenek, fiyat ve KDV sipariş anındaki kopyadır. */
class StoreOrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(StoreReturnItem::class, 'order_item_id');
    }
}
