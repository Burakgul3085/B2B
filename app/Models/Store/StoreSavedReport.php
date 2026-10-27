<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** Mağaza yöneticisinin kayıtlı rapor filtreleri (her yönetici yalnızca kendi kayıtlarını görür). */
class StoreSavedReport extends Model
{
    public const MAX_PER_ADMIN = 30;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
