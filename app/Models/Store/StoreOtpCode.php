<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** SMS doğrulama kodu (özet olarak saklanır). */
class StoreOtpCode extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'attempts' => 'integer'];
    }
}
