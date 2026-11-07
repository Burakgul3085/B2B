<?php

namespace App\Domain\Store\Admins;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Models\AuthToken;
use App\Models\Store\StoreAdmin;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mağaza yöneticisi hesapları (docs/13-magaza-mimarisi.md §5).
 *
 * $actor null ise işlemi platform yöneticisi yapıyordur (platform paneli, iki adımlı doğrulamadan geçmiş).
 *  - Platform: süper admin ve yönetici açar; herkesi pasife alır, şifresini ve bilgilerini değiştirir.
 *  - Süper admin: yalnızca yönetici açar; yalnızca yöneticileri yönetir. Kendini ve diğer süper adminleri yönetemez.
 *  - Yönetici: kimseyi yönetemez; yalnızca kendi profilini (Profil ve Güvenlik) değiştirir.
 * Silme: süper admin yalnızca yöneticileri, platform herkesi siler; son etkin süper admin silinemez (mağaza yönetimsiz
 * kalmasın). İşlem geçmişindeki kayıtlar kalır ("Silinmiş yönetici" görünür); silinenin adı ve e-postası geçmişe yazılır.
 */
class StoreAdminService
{
    public function __construct(private AuditLogger $audit, private AuthTokenService $tokens) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @throws DomainException
     */
    public function create(array $data, bool $super, ?StoreAdmin $actor): StoreAdmin
    {
        if ($actor && (! $actor->is_super || $super)) {
            throw new DomainException('Yönetici açma yetkiniz yok.');
        }

        $email = $this->normalize($data['email']);
        $this->assertEmailAvailable($email);

        return DB::transaction(function () use ($data, $email, $super, $actor) {
            $admin = StoreAdmin::create([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => $data['password'],
                'is_super' => $super,
                'is_active' => true,
                'created_by_type' => $actor ? Area::StoreAdmin->value : Area::Platform->value,
                'created_by_id' => $actor?->id,
            ]);

            $this->audit->log('store.admin_created', $admin, [], ['name' => $admin->name, 'email' => $admin->email, 'is_super' => $super]);

            return $admin;
        });
    }

    /** @throws DomainException */
    public function setActive(StoreAdmin $target, bool $active, ?StoreAdmin $actor): void
    {
        $this->assertCanManage($target, $actor);

        if ($target->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($target, $active) {
            // Pasife alınan kişinin "beni hatırla" oturumları da düşer; açık oturumu bir sonraki istekte kapanır.
            $target->forceFill(['is_active' => $active, 'remember_token' => Str::random(60)])->save();
            $this->tokens->revoke(Area::StoreAdmin, $target->id, 'password_reset');
            $this->audit->log($active ? 'store.admin_activated' : 'store.admin_deactivated', $target, ['is_active' => ! $active], ['is_active' => $active]);
        });
    }

    /** @throws DomainException */
    public function setPassword(StoreAdmin $target, string $password, ?StoreAdmin $actor): void
    {
        $this->assertCanManage($target, $actor);

        DB::transaction(function () use ($target, $password) {
            $target->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $this->tokens->revoke(Area::StoreAdmin, $target->id, 'password_reset');
            $this->audit->log('store.admin_password_set', $target);
        });
    }

    /**
     * Ad ve giriş e-postası değişikliği (yetkili tarafından).
     *
     * @param  array{name: string, email: string}  $data
     *
     * @throws DomainException
     */
    public function update(StoreAdmin $target, array $data, ?StoreAdmin $actor): void
    {
        $this->assertCanManage($target, $actor);

        $email = $this->normalize($data['email']);
        $this->assertEmailAvailable($email, $target->id);

        DB::transaction(function () use ($target, $data, $email) {
            $old = ['name' => $target->name, 'email' => $target->email];
            $values = ['name' => trim($data['name']), 'email' => $email];
            if ($email !== $target->email) {
                $values['remember_token'] = Str::random(60);
                $this->tokens->revoke(Area::StoreAdmin, $target->id, 'password_reset');
            }
            $target->forceFill($values)->save();
            $this->audit->log('store.admin_updated', $target, $old, ['name' => $target->name, 'email' => $target->email]);
        });
    }

    /**
     * Hesabı kalıcı olarak siler. Açık oturumu bir sonraki istekte düşer (kullanıcı bulunamaz); kayıtlı raporları silinir,
     * yanıtları ve ödeme kodları "silinmiş yönetici" ile kalır.
     *
     * @throws DomainException
     */
    public function delete(StoreAdmin $target, ?StoreAdmin $actor): void
    {
        $this->assertCanManage($target, $actor);

        DB::transaction(function () use ($target) {
            // Etkin süper adminler kilitlenir: iki eşzamanlı silme son süper admini birlikte götüremez.
            $supers = StoreAdmin::where('is_super', true)->where('is_active', true)->lockForUpdate()->pluck('id');
            $target = StoreAdmin::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($target->is_super && $target->is_active && $supers->reject(fn ($id) => $id === $target->id)->isEmpty()) {
                throw new DomainException('Son etkin süper admin silinemez; önce başka bir süper admin açın.');
            }

            AuthToken::where('guard', Area::StoreAdmin->value)->where('user_id', $target->id)->delete();
            $this->audit->log('store.admin_deleted', $target, ['name' => $target->name, 'email' => $target->email, 'is_super' => $target->is_super], []);
            $target->delete();
        });
    }

    public function canManage(StoreAdmin $target, ?StoreAdmin $actor): bool
    {
        if ($actor === null) {
            return true; // platform
        }

        return $actor->is_super && $actor->is_active && ! $target->is_super && $target->id !== $actor->id;
    }

    /** @throws DomainException */
    private function assertCanManage(StoreAdmin $target, ?StoreAdmin $actor): void
    {
        if (! $this->canManage($target, $actor)) {
            throw new DomainException($target->is_super
                ? 'Süper admin hesabını yalnızca platform yöneticisi değiştirebilir.'
                : 'Bu hesap üzerinde işlem yetkiniz yok.');
        }
    }

    /** @throws DomainException */
    private function assertEmailAvailable(string $email, ?int $exceptId = null): void
    {
        if (StoreAdmin::where('email', $email)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) {
            throw new DomainException('Bu e-posta adresi başka bir mağaza yöneticisi hesabında kullanılıyor.');
        }
    }

    private function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
