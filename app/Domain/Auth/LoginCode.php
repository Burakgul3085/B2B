<?php

namespace App\Domain\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Mail\BusinessMailer;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Tenancy\TenantContext;
use DomainException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * E-postayla giriş doğrulaması (toptancı, bayi, mağaza yönetimi; platformda doğrulama uygulaması kalır).
 * Parola doğruysa oturum açılmaz; kişinin kendi e-postasına 4 haneli rastgele kod gider ve kod girilince giriş tamamlanır.
 * Bekleyen giriş yalnızca o alanın oturumunda tutulur (kodun kendisi değil, HMAC özeti). Kod 10 dakika geçerlidir,
 * 5 yanlış denemede veya süre dolunca giriş baştan yapılır; yeniden gönderim 60 saniyede bir, en fazla 5 kez.
 * E-posta: toptancı ve bayiye toptancının kurumsal şablonuyla (BusinessMailer), mağaza yöneticisine mağaza şablonuyla.
 * Acil durumda sunucuda LOGIN_EMAIL_CODE=false ile kapatılabilir (config b2b.login_code).
 */
class LoginCode
{
    public const AREAS = [Area::Business, Area::Dealer, Area::StoreAdmin];

    private const KEY = 'login_code';

    private const TTL = 600;

    private const MAX_ATTEMPTS = 5;

    private const MAX_SENDS = 5;

    private const RESEND_AFTER = 60;

    public function __construct(private AccountEmailService $accounts, private TenantContext $tenant, private AuditLogger $audit) {}

    public static function appliesTo(Area $area): bool
    {
        return in_array($area, self::AREAS, true) && config('b2b.login_code');
    }

    /**
     * Parolası doğrulanan kişi için kodu üretip gönderir ve girişi bekletir.
     *
     * @throws DomainException e-posta gönderilemedi
     */
    public function start(Area $area, Model $user, Session $session): void
    {
        $session->put(self::KEY, ['area' => $area->value, 'user_id' => $user->id, 'sends' => 0, 'attempts' => 0]);
        $this->send($area, $user, $session);
    }

    /** @return array{area: string, user_id: int, email: string, sent_at: int, sends: int}|null bu alan için bekleyen giriş */
    public function pending(Area $area, Session $session): ?array
    {
        $state = $session->get(self::KEY);

        return is_array($state) && ($state['area'] ?? null) === $area->value && isset($state['hash']) ? $state : null;
    }

    /** @throws DomainException */
    public function resend(Area $area, Model $user, Session $session): void
    {
        $state = $this->pending($area, $session) ?? throw new DomainException('Giriş süresi doldu, lütfen yeniden giriş yapın.');
        $wait = $state['sent_at'] + self::RESEND_AFTER - now()->timestamp;
        if ($wait > 0) {
            throw new DomainException("Yeni kod için {$wait} saniye bekleyin.");
        }
        if ($state['sends'] >= self::MAX_SENDS) {
            throw new DomainException('Çok fazla kod istendi. Lütfen birkaç dakika sonra yeniden giriş yapın.');
        }
        $this->send($area, $user, $session);
    }

    /**
     * Kodu doğrular; doğruysa bekleyen giriş silinir ve kişinin kimliği döner.
     *
     * @throws DomainException yanlış veya süresi dolmuş kod
     */
    public function verify(Area $area, string $code, Session $session): int
    {
        $state = $this->pending($area, $session) ?? throw new DomainException('Giriş süresi doldu, lütfen yeniden giriş yapın.');

        if (now()->timestamp > $state['expires_at']) {
            $session->forget(self::KEY);
            throw new DomainException('Kodun süresi doldu, lütfen yeniden giriş yapın.');
        }
        if (! hash_equals($state['hash'], $this->hash($state, $code))) {
            $state['attempts']++;
            if ($state['attempts'] >= self::MAX_ATTEMPTS) {
                $session->forget(self::KEY);
                $this->audit->log('auth.login_code_failed', null, [], ['area' => $area->value, 'user_id' => $state['user_id']], actorArea: $area);
                throw new DomainException('Çok fazla yanlış kod girildi, lütfen yeniden giriş yapın.');
            }
            $session->put(self::KEY, $state);
            $left = self::MAX_ATTEMPTS - $state['attempts'];
            throw new DomainException("Kod hatalı. {$left} deneme hakkınız kaldı.");
        }

        $session->forget(self::KEY);

        return (int) $state['user_id'];
    }

    public function cancel(Session $session): void
    {
        $session->forget(self::KEY);
    }

    /** "b***@gmail.com" */
    public static function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).str_repeat('*', max(2, min(6, mb_strlen($name) - 1))).'@'.$domain;
    }

    private function send(Area $area, Model $user, Session $session): void
    {
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $state = $session->get(self::KEY);
        $state['nonce'] = bin2hex(random_bytes(8));
        $state['hash'] = $this->hash($state, $code);
        $state['expires_at'] = now()->timestamp + self::TTL;
        $state['sent_at'] = now()->timestamp;
        $state['sends']++;
        $state['attempts'] = 0;
        $state['email'] = self::maskEmail($user->email);

        if (! $this->deliver($area, $user, $code)) {
            $session->forget(self::KEY);
            throw new DomainException('Doğrulama kodu e-postası gönderilemedi. Lütfen biraz sonra tekrar deneyin veya yöneticinize bildirin.');
        }
        $session->put(self::KEY, $state);
    }

    private function deliver(Area $area, Model $user, string $code): bool
    {
        if ($area === Area::StoreAdmin) {
            if (app()->environment('local')) {
                Log::channel('single')->info("[store-login-code] {$user->email}", ['code' => $code]); // yerel geliştirmede e-posta yerine
            }

            return app(StoreMailer::class)->send($user->email, 'Mağaza yönetimi giriş doğrulama kodunuz', [
                'tagline' => 'Mağaza yönetimi', 'eyebrow' => 'Giriş doğrulaması', 'title' => 'Giriş doğrulama kodunuz',
                'intro' => "Merhaba {$user->name},\n\nMağaza yönetimine giriş yapılıyor. Girişi tamamlamak için aşağıdaki 4 haneli kodu giriş ekranına yazın.",
                'code' => $code,
                'outro' => 'Bu girişi siz yapmadıysanız kodu kimseyle paylaşmayın ve şifrenizi hemen değiştirin.',
            ]);
        }

        $businessId = $this->accounts->mailingBusinessFor($user);
        if ($businessId === null) {
            return false;
        }

        return $this->tenant->runAs($businessId, fn () => app(BusinessMailer::class)->sendNow('login_code', $user->email, [], $code));
    }

    /** Kodun oturumdaki özeti: uygulama anahtarı ve girişe özgü rastgele değerle (oturum verisi sızsa da kod çıkarılamaz). */
    private function hash(array $state, string $code): string
    {
        return hash_hmac('sha256', $state['area'].'|'.$state['user_id'].'|'.$state['nonce'].'|'.$code, (string) config('app.key'));
    }
}
