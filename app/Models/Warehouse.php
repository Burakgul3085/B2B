<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use BelongsToBusiness;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['code', 'name', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
