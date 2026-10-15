<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Toptancı–bayi ilişkisinin cari hesabı. Bakiye (pozitif = bayi borçlu) yalnızca cari hareketleriyle,
 * App\Domain\Ledger\LedgerService üzerinden değişir. Resmî muhasebe kaydı değildir.
 */
class CurrentAccount extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['dealer_relationship_id', 'code'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(DealerRelationship::class, 'dealer_relationship_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CurrentAccountMovement::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(CollectionEntry::class);
    }
}
