<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Türkiye ili. Kimlik plaka kodudur. Sabit başvuru verisidir. */
class Province extends Model
{
    public const ESKISEHIR = 26;

    protected $table = 'tr_provinces';

    public $incrementing = false;

    public $timestamps = false;

    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'province_id')->orderBy('name');
    }
}
