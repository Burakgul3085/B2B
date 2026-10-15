<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use BelongsToBusiness;

    protected $attributes = ['allows_fraction' => false];

    protected $fillable = ['code', 'name', 'allows_fraction'];

    protected function casts(): array
    {
        return ['allows_fraction' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
