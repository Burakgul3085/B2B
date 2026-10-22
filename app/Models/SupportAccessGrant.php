<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Toptancının platform yöneticisine verdiği süreli destek erişimi (docs/01-analiz-ve-mimari.md, "Platform yöneticisinin
 * ticari veriye erişimi"). İşletme bağlamı olmadan da okunduğu için işletme kapsamı yoktur; sorgular business_id ile süzülür.
 */
class SupportAccessGrant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
