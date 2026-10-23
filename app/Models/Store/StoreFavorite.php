<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Müşterinin favori ürünü (yalnızca üyeler). */
class StoreFavorite extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'product_id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }
}
