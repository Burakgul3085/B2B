<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Uygulama hatası (aynı sınıf + dosya + satır tek kayıtta toplanır). Platform → Hata Kayıtları. */
class ErrorEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime', 'occurrences' => 'integer'];
    }
}
