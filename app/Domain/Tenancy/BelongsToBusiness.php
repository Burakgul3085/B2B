<?php

namespace App\Domain\Tenancy;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * İşletmeye ait modeller: her sorgu aktif işletmeyle sınırlanır, yeni kayıtlara
 * işletme otomatik atanır, başka işletmeye kayıt yazma girişimi reddedilir.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BusinessScope);

        static::creating(function ($model) {
            $context = app(TenantContext::class);
            $current = $context->businessId();

            if ($model->business_id === null) {
                $model->business_id = $current;
            } elseif ((int) $model->business_id !== $current) {
                throw new LogicException('Kayıt aktif işletme dışında oluşturulamaz.');
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('business_id')) {
                throw new LogicException('Kaydın işletmesi değiştirilemez.');
            }
        });
    }

    /**
     * İşletme kapsamını bilerek kaldırır. Yalnızca platform modülünde ve
     * bayinin kendi ilişkilerini listelemek gibi açıkça sınırlandırılmış sorgularda kullanılır.
     */
    public static function withoutBusinessScope(): Builder
    {
        return static::withoutGlobalScope(BusinessScope::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
