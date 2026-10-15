<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['product_id', 'path', 'sort', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
