<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Aynı ürünün renk/boyut gibi varyantlarını gruplar; her varyant kendi SKU'su olan ayrı üründür (K8). */
class VariantGroup extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['name', 'attribute_names'];

    protected function casts(): array
    {
        return ['attribute_names' => 'array'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
