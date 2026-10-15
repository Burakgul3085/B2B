<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['import_job_id', 'row_no', 'column', 'message'];
}
