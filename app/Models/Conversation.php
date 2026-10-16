<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Toptancı ile bir bayi arasındaki konuşma. Başka işletme veya başka bayi göremez. */
class Conversation extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['dealer_relationship_id', 'subject', 'context_type', 'context_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(DealerRelationship::class, 'dealer_relationship_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ConversationRead::class);
    }

    public function contextLabel(): ?string
    {
        return match ($this->context_type) {
            'order' => 'Sipariş '.Order::whereKey($this->context_id)->value('order_no'),
            'product_request' => 'Ürün talebi #'.$this->context_id,
            default => null,
        };
    }
}
