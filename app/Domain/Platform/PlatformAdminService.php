<?php

namespace App\Domain\Platform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Models\AuthToken;
use App\Models\PlatformAdmin;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Platform yöneticisi hesapları. Hesap açma `platform:create-admin` komutuyla yapılır; panelden yalnızca silinir.
 * Kimse kendi hesabını silemez ve son etkin platform yöneticisi silinemez (platform yönetimsiz kalmasın).
 */
class PlatformAdminService
{
    public function __construct(private AuditLogger $audit) {}

    public function canDelete(PlatformAdmin $target, PlatformAdmin $actor): bool
    {
        return $actor->is_active && $target->id !== $actor->id;
    }

    /** @throws DomainException */
    public function delete(PlatformAdmin $target, PlatformAdmin $actor): void
    {
        if (! $this->canDelete($target, $actor)) {
            throw new DomainException('Kendi hesabınızı silemezsiniz.');
        }

        DB::transaction(function () use ($target) {
            // Etkin yöneticiler kilitlenir: iki eşzamanlı silme son yöneticiyi birlikte götüremez.
            $active = PlatformAdmin::where('is_active', true)->lockForUpdate()->pluck('id');
            $target = PlatformAdmin::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($target->is_active && $active->reject(fn ($id) => $id === $target->id)->isEmpty()) {
                throw new DomainException('Son etkin platform yöneticisi silinemez.');
            }

            AuthToken::where('guard', Area::Platform->value)->where('user_id', $target->id)->delete();
            $this->audit->log('platform.admin_deleted', $target, ['name' => $target->name, 'email' => $target->email], []);
            $target->delete();
        });
    }
}
