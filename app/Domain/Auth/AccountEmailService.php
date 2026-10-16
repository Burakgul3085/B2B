<?php

namespace App\Domain\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Mail\SystemMailer;
use App\Models\AuthToken;
use App\Models\BusinessUser;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Giriş e-postası ve parola sıfırlama işlemleri (üç alan için ortak).
 *
 *  - Kişi kendi e-postasını değiştirir: yeni adrese doğrulama bağlantısı gider; tıklanana kadar eski adres geçerlidir.
 *    Doğrulanınca eski adrese bildirim gider ve "beni hatırla" oturumları sonlanır.
 *  - Platform yöneticisi doğrudan değiştirir: gerekçe zorunlu, denetim kaydı, eski ve yeni adrese bildirim.
 *  - Parola hiçbir yöneticiye gösterilmez; yalnızca sıfırlama bağlantısı üretilir.
 *
 * Bağlantıların ekranda gösterilmesi: sistem e-postası tanımlı değilse ve ortam üretim değilse (yerel/demo)
 * bağlantı ekranda gösterilir. Üretimde bağlantı yalnızca e-postayla gider.
 */
class AccountEmailService
{
    public function __construct(private AuthTokenService $tokens, private SystemMailer $mailer, private AuditLogger $audit) {}

    /** Sistem e-postası olmayan yerel/demo ortamında bağlantılar ekranda gösterilebilir. */
    public function revealsLinks(): bool
    {
        return ! $this->mailer->isConfigured() && ! app()->isProduction();
    }

    /**
     * @return array{url: ?string, email: string} url yalnızca ekranda gösterilecekse dolu
     */
    public function requestChange(Area $area, Model $user, string $newEmail): array
    {
        $email = $this->normalize($newEmail);
        if ($email === Str::lower($user->email)) {
            throw new DomainException('Yeni e-posta adresi mevcut adresinizle aynı.');
        }
        $this->assertAvailable($area, $user, $email);

        $plain = $this->tokens->issue($area, $user->id, 'email_change', $email);
        $url = $this->tokens->url($area, 'email_change', $plain);
        $this->mailer->send($email, 'E-posta adresinizi doğrulayın', [
            'title' => 'Yeni e-posta adresinizi doğrulayın',
            'intro' => "{$area->label()} hesabınızın giriş e-postasını bu adresle değiştirme talebi aldık. Değişikliği tamamlamak için 24 saat içinde aşağıdaki bağlantıyı açın. Doğrulayana kadar eski adresinizle giriş yapmaya devam edersiniz.",
            'action_url' => $url,
            'action_label' => 'E-postamı doğrula',
            'outro' => 'Bu talebi siz yapmadıysanız bu e-postayı dikkate almayın.',
        ]);
        $this->audit->log('auth.email_change_requested', $user, [], ['new_email' => $email], businessId: $this->businessId($user), actorArea: $area);

        return ['url' => $this->revealsLinks() ? $url : null, 'email' => $email];
    }

    /** Doğrulama bağlantısıyla değişikliği tamamlar. @throws DomainException */
    public function confirm(Area $area, AuthToken $token): Model
    {
        return DB::transaction(function () use ($area, $token) {
            $model = $area->model();
            $user = $model::whereKey($token->user_id)->lockForUpdate()->firstOrFail();
            if (! $this->tokens->consume($token)) {
                throw new DomainException('Bu bağlantı zaten kullanılmış.');
            }
            $this->assertAvailable($area, $user, $token->new_email);

            $old = $user->email;
            $this->applyEmail($user, $token->new_email);
            $this->audit->log('auth.email_changed', $user, ['email' => $old], ['email' => $user->email], businessId: $this->businessId($user), actorArea: $area);
            DB::afterCommit(fn () => $this->notifyOldAddress($area, $old, $user->email, 'Değişikliği siz yaptınız.'));

            return $user;
        });
    }

    /** Platform yöneticisi giriş e-postasını doğrudan değiştirir. @throws DomainException */
    public function changeByPlatform(Area $area, Model $user, string $newEmail, string $reason): void
    {
        $email = $this->normalize($newEmail);
        if ($email === Str::lower($user->email)) {
            throw new DomainException('Yeni e-posta adresi mevcut adresle aynı.');
        }

        DB::transaction(function () use ($area, $user, $email, $reason) {
            $user = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertAvailable($area, $user, $email);
            $old = $user->email;
            $this->applyEmail($user, $email);
            $this->tokens->revoke($area, $user->id, 'email_change');
            // Henüz etkinleşmemiş hesapta eski adrese gitmiş aktivasyon bağlantıları geçersiz olur.
            $this->tokens->revoke($area, $user->id, 'activation');
            $this->tokens->revoke($area, $user->id, 'password_reset');
            $this->audit->log('platform.user_email_changed', $user, ['email' => $old], ['email' => $email, 'reason' => $reason], businessId: $this->businessId($user));

            DB::afterCommit(function () use ($area, $old, $email) {
                $this->notifyOldAddress($area, $old, $email, 'Değişiklik platform yöneticisi tarafından yapıldı.');
                $this->mailer->send($email, 'Hesabınız güncellendi', [
                    'title' => 'Giriş e-postanız bu adres olarak güncellendi',
                    'intro' => "{$area->label()} hesabınızın giriş e-postası platform yöneticisi tarafından bu adres olarak değiştirildi. Bundan sonra bu adresle giriş yapabilirsiniz.",
                ]);
            });
        });
    }

    /**
     * Parola sıfırlama bağlantısı oluşturur ve hesabın e-postasına gönderir.
     *
     * @return array{url: ?string} url yalnızca ekranda gösterilecekse (yerel/demo veya $reveal) dolu
     */
    public function sendPasswordReset(Area $area, Model $user, string $requestedBy, bool $reveal = false): array
    {
        $plain = $this->tokens->issue($area, $user->id, 'password_reset');
        $url = $this->tokens->url($area, 'password_reset', $plain);
        $this->mailer->send($user->email, 'Parola sıfırlama', [
            'title' => 'Yeni parolanızı belirleyin',
            'intro' => "{$requestedBy} {$area->label()} hesabınız için parola sıfırlama bağlantısı gönderdi. Bağlantı ".config('b2b.password_reset_ttl_minutes').' dakika geçerlidir.',
            'action_url' => $url,
            'action_label' => 'Yeni parola belirle',
            'outro' => 'Parolanızı değiştirmek istemiyorsanız bu e-postayı dikkate almayın; mevcut parolanız geçerli kalır.',
        ]);

        return ['url' => $reveal || $this->revealsLinks() ? $url : null];
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

    private function applyEmail(Model $user, string $email): void
    {
        // "Beni hatırla" çerezleri geçersiz olur.
        $user->forceFill(['email' => $email, 'remember_token' => Str::random(60)])->save();
    }

    private function notifyOldAddress(Area $area, string $oldEmail, string $newEmail, string $who): void
    {
        $masked = preg_replace('/(?<=.).(?=[^@]*@)/u', '•', $newEmail);
        $this->mailer->send($oldEmail, 'Giriş e-postanız değiştirildi', [
            'title' => 'Giriş e-postanız değiştirildi',
            'intro' => "{$area->label()} hesabınızın giriş e-postası {$masked} olarak değiştirildi. {$who} Bu değişikliği siz yapmadıysanız hemen platform yöneticisiyle iletişime geçin.",
        ]);
    }

    private function businessId(Model $user): ?int
    {
        return $user instanceof BusinessUser ? $user->business_id : null;
    }
}
