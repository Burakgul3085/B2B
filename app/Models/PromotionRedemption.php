<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kupon / kampanya kullanımı. Sipariş reddedilir veya iptal edilirse released_at dolar ve kullanım sayılmaz. */
class PromotionRedemption extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'released_at' => 'datetime'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(DealerRelationship::class, 'dealer_relationship_id');
    }
}
