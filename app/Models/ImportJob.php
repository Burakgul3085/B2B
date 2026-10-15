<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportJob extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['type', 'original_name', 'status', 'total_rows', 'created_rows', 'updated_rows', 'failed_rows', 'created_by', 'finished_at'];

    protected function casts(): array
    {
        return ['finished_at' => 'datetime'];
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class)->orderBy('row_no');
    }
}
