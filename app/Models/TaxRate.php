<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRate extends Model
{
    use BelongsToBusiness;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'rate', 'is_active'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
