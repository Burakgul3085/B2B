<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ödeme sırasında geçici stok ayırma. Süresi dolan, bırakılan veya satışa dönüşen ayırma etkin değildir. */
class StoreStockHold extends Model
{
    protected $fillable = ['variant_id', 'holder', 'quantity', 'expires_at', 'released_at', 'converted_at'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'expires_at' => 'datetime', 'released_at' => 'datetime', 'converted_at' => 'datetime'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreProductVariant::class, 'variant_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at')->whereNull('converted_at')->where('expires_at', '>', now());
    }
}
