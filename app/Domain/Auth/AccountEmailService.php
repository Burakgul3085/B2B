<?php

namespace App\Domain\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Mail\BusinessMailer;
use App\Domain\Mail\SystemMailer;
use App\Domain\Tenancy\TenantContext;
use App\Models\BusinessUser;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use App\Models\EmailSetting;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hesap bilgileri (üç alan için ortak). Sade kurgu:
 *  - Kişi kendi giriş e-postasını mevcut şifresiyle onaylayarak hemen değiştirir (doğrulama bağlantısı yok).
 *  - Toptancı bayisine, platform her hesaba yeni şifre belirleyebilir; platform e-postayı da değiştirebilir.
 *  - Şifresini unutan, sıfırlama bağlantısını kendi e-postasına alır; e-posta ilgili toptancının ana Gmail
 *    hesabından gider (ayrı bir sistem e-postası yoktur).
 * Her değişiklik denetim kaydına yazılır.
 */
class AccountEmailService
{
    public function __construct(private AuthTokenService $tokens, private AuditLogger $audit, private TenantContext $tenant) {}

    /** @throws DomainException */
    public function changeOwnEmail(Area $area, Model $user, string $newEmail): string
    {
        return $this->changeEmail($area, $user, $newEmail, 'auth.email_changed', []);
    }

    /** @throws DomainException */
    public function changeByPlatform(Area $area, Model $user, string $newEmail, string $reason): string
    {
        return $this->changeEmail($area, $user, $newEmail, 'platform.user_email_changed', ['reason' => $reason]);
    }

    /**
     * Yetkilinin (toptancı veya platform) belirlediği yeni şifre. Hiç şifresi olmayan (eski aktivasyon bekleyen)
     * hesap bununla etkinleşir. "Beni hatırla" oturumları sonlanır.
     */
    public function setPassword(Area $area, Model $user, string $password, string $action, array $context = []): void
    {
        DB::transaction(function () use ($area, $user, $password, $action, $context) {
            $user = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $values = ['password' => $password, 'remember_token' => Str::random(60)];
            if ($user->status === 'pending_activation') {
                $values += ['status' => 'active', 'activated_at' => now()];
            }
            $user->forceFill($values)->save();
            $this->tokens->revoke($area, $user->id, 'password_reset');
            $this->audit->log($action, $user, [], $context, businessId: $this->businessId($user));
        });
    }

    /**
     * "Parolamı unuttum": bağlantı kişinin kendi e-postasına, ilgili toptancının Gmail hesabından gider.
     *
     * @return bool gönderim kuyruğa alındı mı
     */
    public function sendPasswordResetLink(Area $area, Model $user): bool
    {
        $url = $this->tokens->url($area, 'password_reset', $this->tokens->issue($area, $user->id, 'password_reset'));

        if (in_array($area, [Area::Platform, Area::StoreAdmin, Area::Customer], true)) {
            // Platform ve mağaza hesapları B2B işletmesine bağlı değildir; yalnızca sunucuda tanımlıysa sistem e-postası kullanılır.
            $label = match ($area) {
                Area::Platform => 'Platform yönetimi', Area::StoreAdmin => 'Mağaza yönetimi', default => config('store.name').' mağaza'
            };

            return app(SystemMailer::class)->send($user->email, 'Parola sıfırlama', [
                'title' => 'Parolanızı sıfırlayın', 'intro' => "{$label} hesabınız için parola sıfırlama talebi aldık.",
                'action_url' => $url, 'action_label' => 'Yeni parola belirle',
            ]);
        }

        $businessId = $this->mailingBusinessFor($user);
        if ($businessId === null) {
            return false;
        }

        return $this->tenant->runAs($businessId, fn () => app(BusinessMailer::class)
            ->queue('password_reset', $user->email, [], ['url' => $url, 'label' => 'Yeni parola belirle'])->status !== 'skipped');
    }

    /** @throws DomainException */
    public function assertAvailable(Area $area, Model $user, string $email): void
    {
        $model = $area->model();
        if ($model::where('email', $email)->whereKeyNot($user->id)->exists()) {
            throw new DomainException('Bu e-posta adresi başka bir hesapta kullanılıyor.');
        }
    }

    public function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function changeEmail(Area $area, Model $user, string $newEmail, string $action, array $context): string
    {
        $email = $this->normalize($newEmail);
        if ($email === Str::lower($user->email)) {
            throw new DomainException('Yeni e-posta adresi mevcut adresle aynı.');
        }

        DB::transaction(function () use ($area, $user, $email, $action, $context) {
            $user = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertAvailable($area, $user, $email);
            $old = $user->email;
            // "Beni hatırla" çerezleri geçersiz olur; açık oturum devam eder.
            $user->forceFill(['email' => $email, 'remember_token' => Str::random(60)])->save();
            $this->tokens->revoke($area, $user->id, 'password_reset');
            $this->audit->log($action, $user, ['email' => $old], ['email' => $email] + $context, businessId: $this->businessId($user), actorArea: $action === 'auth.email_changed' ? $area : null);
        });

        return $email;
    }

    /** Toptancı kullanıcısı için kendi işletmesi; bayi için Gmail ayarı olan ilk etkin toptancısı. */
    public function mailingBusinessFor(Model $user): ?int
    {
        if ($user instanceof BusinessUser) {
            return $user->business_id;
        }
        if ($user instanceof DealerUser) {
            $businessIds = DealerRelationship::withoutBusinessScope()->where('dealer_id', $user->dealer_id)
                ->where('status', 'active')->orderBy('id')->pluck('business_id');

            return EmailSetting::withoutBusinessScope()->whereIn('business_id', $businessIds)->where('status', '!=', 'failed')
                ->orderByRaw('FIELD(business_id, '.($businessIds->implode(',') ?: '0').')')->value('business_id');
        }

        return null;
    }

    private function businessId(Model $user): ?int
    {
        return $user instanceof BusinessUser ? $user->business_id : null;
    }
}
