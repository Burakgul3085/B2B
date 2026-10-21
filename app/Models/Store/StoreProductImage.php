<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreProductImage extends Model
{
    protected $attributes = ['sort' => 0];

    protected $fillable = ['product_id', 'path', 'sort'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }
}
