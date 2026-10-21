<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** Sipariş durum geçmişi. Yalnızca eklenir. */
class StoreOrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];
}
