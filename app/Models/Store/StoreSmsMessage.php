<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** Gönderilen SMS kaydı (doğrulama kodları maskelenir). */
class StoreSmsMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];
}
