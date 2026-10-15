<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class BusinessCounter extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['key', 'value'];
}
