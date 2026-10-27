<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** İletişim mesajına yönetimden e-postayla gönderilen yanıt. */
class StoreContactReply extends Model
{
    protected $fillable = ['admin_id', 'admin_name', 'body'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(StoreContactMessage::class, 'contact_message_id');
    }
}
