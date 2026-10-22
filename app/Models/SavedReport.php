<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** Kullanıcının kaydettiği rapor filtreleri (toptancı veya bayi kullanıcısı; yalnızca sahibi görür). */
class SavedReport extends Model
{
    use BelongsToBusiness;

    public const MAX_PER_USER = 30;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
