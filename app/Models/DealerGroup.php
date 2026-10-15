<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class DealerGroup extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['name'];
}
