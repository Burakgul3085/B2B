<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Toplu üretilmiş, tek kullanımlık kupon kodu. Kurallar bağlı olduğu indirimdedir. */
class StoreDiscountCode extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(StoreDiscount::class, 'discount_id');
    }
}
