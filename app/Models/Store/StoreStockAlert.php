<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Stoğa gelince haber ver" kaydı. E-posta yalnızca bu bildirim için kullanılır; gönderildikten sonra silinir. */
class StoreStockAlert extends Model
{
    protected $fillable = ['variant_id', 'customer_id', 'email'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreProductVariant::class, 'variant_id');
    }
}
