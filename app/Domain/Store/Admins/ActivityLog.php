<?php

namespace App\Domain\Store\Admins;

use App\Domain\Audit\AuditLogger;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mağaza işlem geçmişi: mağaza yöneticilerinin işlemleri ve platformun mağaza hesaplarına yaptığı değişiklikler
 * ("store.*"). Kayıtları yalnızca süper admin silebilir; her silme, kaç kaydın silindiğini yazan yeni bir kayıt bırakır.
 */
class ActivityLog
{
    public function __construct(private AuditLogger $audit) {}

    public static function query(): Builder
    {
        return AuditLog::query()->where(fn ($q) => $q
            ->where('actor_type', 'store_admin')
            ->orWhere('action', 'like', 'store.%'));
    }

    /**
     * Seçilen kayıtları siler (yalnızca mağaza işlem geçmişine ait olanlar; B2B kayıtlarına dokunulmaz).
     *
     * @param  array<int, int|string>  $ids
     */
    public function deleteMany(array $ids): int
    {
        return $this->record(self::query()->whereIn('id', array_map('intval', $ids))->delete());
    }

    /** Mağaza işlem geçmişinin tamamını siler. */
    public function deleteAll(): int
    {
        return $this->record(self::query()->delete());
    }

    private function record(int $count): int
    {
        if ($count > 0) {
            $this->audit->log('store.activity_deleted', null, [], ['count' => $count]);
        }

        return $count;
    }
}
