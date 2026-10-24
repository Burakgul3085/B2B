<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bir kampanyanın bir aboneye gönderimi (aynı aboneye iki kez gitmez). */
class StoreNewsletterDelivery extends Model
{
    public $timestamps = false;

    protected $attributes = ['status' => 'pending'];

    protected $fillable = ['campaign_id', 'subscriber_id', 'status', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(StoreNewsletterSubscriber::class, 'subscriber_id');
    }
}
