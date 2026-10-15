<?php

namespace App\Domain\Platform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Domain\Mail\SystemMailer;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\BusinessUser;
use Illuminate\Support\Facades\DB;

/**
 * Platform yöneticisi yeni bir toptancı işletmesi açar: işletme kaydı, varsayılan ayarlar,
 * modüller, ilk lisans ve tek işletme kullanıcısı tek transaction'da oluşur.
 * Kod değişikliği gerekmez (kabul koşulu 18).
 */
class CreateBusiness
{
    public function __construct(
        private AuthTokenService $tokens,
        private SystemMailer $mailer,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{business: Business, activation_url: ?string, mail_sent: bool}
     */
    public function handle(array $business, array $owner, array $license): array
    {
        [$model, $user] = DB::transaction(function () use ($business, $owner, $license) {
            $model = Business::create($business + ['status' => 'active']);
            $model->settings()->create([]);

            foreach (array_keys(BusinessModule::AVAILABLE) as $key) {
                $model->modules()->create(['module_key' => $key, 'enabled' => true]);
            }

            $model->licenses()->create($license);

            $user = BusinessUser::create([
                'business_id' => $model->id,
                'name' => $owner['name'],
                'email' => $owner['email'],
                'status' => 'pending_activation',
            ]);

            $this->audit->log('platform.business_created', $model, [], [
                'slug' => $model->slug, 'legal_name' => $model->legal_name, 'owner_email' => $user->email,
            ], businessId: $model->id);

            return [$model, $user];
        });

        $result = $this->sendActivation($user);

        return ['business' => $model] + $result;
    }

    /** @return array{activation_url: ?string, mail_sent: bool} */
    public function sendActivation(BusinessUser $user): array
    {
        $plain = $this->tokens->issue(Area::Business, $user->id, 'activation');
        $url = $this->tokens->url(Area::Business, 'activation', $plain);

        $sent = $this->mailer->send($user->email, 'Toptancı paneli hesabınız hazır', [
            'title' => 'Hesabınızı etkinleştirin',
            'intro' => "{$user->business->display_name} için toptancı paneli hesabınız oluşturuldu. Parolanızı belirlemek için aşağıdaki bağlantıyı kullanın. Bağlantı ".config('b2b.activation_ttl_hours').' saat geçerlidir.',
            'action_url' => $url,
            'action_label' => 'Parolamı belirle',
        ]);

        // Bağlantı yalnızca e-posta gönderilemediğinde ekranda gösterilir; bu durum denetim kaydına yazılır.
        if (! $sent) {
            $this->audit->log('platform.activation_link_shown', $user, businessId: $user->business_id);
        }

        return ['activation_url' => $sent ? null : $url, 'mail_sent' => $sent];
    }
}
