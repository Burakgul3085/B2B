<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Türkiye ilçesi. Sabit başvuru verisidir. */
class District extends Model
{
    protected $table = 'tr_districts';

    public $timestamps = false;

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }
}
