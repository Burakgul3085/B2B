<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealerGroup extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['name', 'price_list_id'];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(DealerRelationship::class);
    }
}
