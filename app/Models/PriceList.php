<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    use BelongsToBusiness;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(DealerGroup::class);
    }

    public function isCurrent(?string $date = null): bool
    {
        $date ??= now()->toDateString();

        return $this->is_active
            && ($this->starts_on === null || $this->starts_on->toDateString() <= $date)
            && ($this->ends_on === null || $this->ends_on->toDateString() >= $date);
    }
}
