<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['key', 'subject', 'intro'];
}
