<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Toptancı ↔ bayi ticari ilişkisi. Fiyat, sipariş ve cari bu kayda bağlıdır. */
class DealerRelationship extends Model
{
    use BelongsToBusiness;

    public const STATUSES = ['active' => 'Aktif', 'suspended' => 'Askıda', 'closed' => 'Kapatıldı'];

    protected $fillable = [
        'dealer_id', 'dealer_code', 'dealer_group_id', 'status', 'payment_term_days',
        'can_edit_addresses', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['can_edit_addresses' => 'boolean', 'approved_at' => 'datetime'];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DealerGroup::class, 'dealer_group_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
