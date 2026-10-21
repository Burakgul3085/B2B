<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** Para iadesi kaydı: iyzico (kartla ödeme) veya elle (kapıda ödemede havale). Başarısız denemeler de saklanır. */
class StoreRefund extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['transactions' => 'array', 'created_at' => 'datetime'];
    }
}
