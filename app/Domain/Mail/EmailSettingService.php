<?php

namespace App\Domain\Mail;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\EmailSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * İşletmenin ana Gmail hesabı (gönderen adres + 16 haneli uygulama şifresi). Bütün e-postalar bu hesaptan gider;
 * toptancıya gelen bildirimler de bu adrese düşer. Toptancı panelinden ve platformdan aynı kurallarla yönetilir.
 * Çağıran, işletme bağlamını kurmuş olmalıdır.
 */
class EmailSettingService
{
    public function __construct(private SecretBox $secrets, private AuditLogger $audit, private TenantContext $tenant) {}

    /** @return bool kimlik bilgileri değişti mi (yeniden test edilmesi gerekir) */
    public function save(array $input): bool
    {
        $setting = EmailSetting::first();
        $input['app_password'] = preg_replace('/\s+/', '', is_scalar($input['app_password'] ?? null) ? (string) $input['app_password'] : '');

        $data = Validator::make($input, [
            'from_email' => ['required', 'string', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:120'],
            // Google uygulama şifresi 16 harftir (boşluksuz). Kayıtlıysa boş bırakılabilir.
            'app_password' => [$setting ? 'nullable' : 'required', 'regex:/^[a-zA-Z]{16}$/'],
            'payment_reminders' => ['nullable', 'boolean'],
        ], ['app_password.regex' => 'Uygulama şifresi 16 harften oluşmalıdır (Google gösterdiği boşluklar olmadan da girebilirsiniz).'],
            ['from_email' => 'ana e-posta adresi', 'from_name' => 'görünen ad', 'app_password' => 'uygulama şifresi'])->validate();

        $values = [
            'from_email' => Str::lower($data['from_email']),
            'from_name' => $data['from_name'] ?? null,
            'payment_reminders' => (bool) ($data['payment_reminders'] ?? $setting?->payment_reminders ?? false),
        ];

        $credentialsChanged = ! $setting || filled($data['app_password'] ?? null) || $setting->from_email !== $values['from_email'];
        if (filled($data['app_password'] ?? null)) {
            $values['app_password_encrypted'] = $this->secrets->encrypt($data['app_password']);
        }
        if ($credentialsChanged) {
            $values += ['status' => 'unverified', 'last_error_code' => null];
        }

        $setting ? $setting->update($values) : EmailSetting::create($values);
        $this->audit->log('email.settings_updated', null, [], ['from_email' => $values['from_email'], 'password_changed' => filled($data['app_password'] ?? null)],
            businessId: $this->tenant->businessId());

        return $credentialsChanged;
    }

    /**
     * Gmail bağlantısı ve kimlik doğrulaması denenir; başarılıysa ana adrese test e-postası gider.
     *
     * @throws MailFailure
     */
    public function test(MailTransport $transport): string
    {
        $setting = EmailSetting::firstOrFail();
        $business = Business::findOrFail($this->tenant->businessId());
        $sender = ['email' => $setting->from_email, 'name' => $setting->from_name, 'password' => $this->secrets->decrypt($setting->app_password_encrypted)];

        try {
            $transport->verify($sender);
            $transport->send($sender, ['to' => $setting->from_email, 'subject' => 'Test e-postası — '.$business->display_name]
                + app(BusinessEmailRenderer::class)->render($business, null, 'E-posta ayarınız çalışıyor', [
                    'intro' => "Tebrikler, Gmail hesabınız {$business->display_name} sistemine başarıyla bağlandı.\n\nBundan sonra bayilerinize giden bütün e-postalar (sipariş durumu, başvuru sonuçları, duyurular, mesaj bildirimleri) bu hesaptan gönderilecek; size gelen bildirimler de bu adrese düşecek.",
                    'details' => ['Gönderen hesap' => $setting->from_email, 'Gönderen adı' => $setting->from_name ?: $business->display_name],
                ]));
        } catch (MailFailure $e) {
            $setting->update(['status' => 'failed', 'last_tested_at' => now(), 'last_error_code' => $e->reason]);
            $this->audit->log('email.test_failed', null, [], ['reason' => $e->reason], businessId: $business->id);

            throw $e;
        }

        $setting->update(['status' => 'verified', 'last_tested_at' => now(), 'last_error_code' => null]);
        $this->audit->log('email.test_succeeded', businessId: $business->id);

        return $setting->from_email;
    }

    /** Toptancıya gelen bildirimlerin gideceği ana adres (ayar yoksa toptancı kullanıcısının e-postası). */
    public static function inboxAddress(?string $fallback): ?string
    {
        return EmailSetting::value('from_email') ?: $fallback;
    }
}
