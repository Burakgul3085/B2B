<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sepet: misafirde tarayıcı belirteciyle, üyede müşteriyle eşleşir. Fiyat saklanmaz. */
class StoreCart extends Model
{
    protected $fillable = ['customer_id', 'token', 'coupon_code'];

    public function items(): HasMany
    {
        return $this->hasMany(StoreCartItem::class, 'cart_id')->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(StoreCustomer::class, 'customer_id');
    }
}
