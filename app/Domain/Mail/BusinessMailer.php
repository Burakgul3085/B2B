<?php

namespace App\Domain\Mail;

use App\Domain\Tenancy\TenantContext;
use App\Jobs\SendBusinessEmail;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Toptancının kendi Gmail hesabıyla gönderilen ticari e-postalar (Bölüm 14).
 *  - Gönderim her zaman kuyrukta ve ilgili işlem commit edildikten SONRA yapılır;
 *    e-posta hatası siparişi, onayı veya başvuruyu asla geri almaz.
 *  - Her gönderim email_logs'a yazılır; başarısız olan tekrar denenebilir.
 *  - E-posta ayarı yoksa veya modül kapalıysa kayıt "atlandı" olarak tutulur; platform e-postası kullanılmaz (K9).
 */
class BusinessMailer
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @param  array<string, scalar|null>  $vars  yer tutucu değerleri
     * @param  array{url?: string, label?: string}  $action
     * @param  array<string, string>  $details  e-postadaki özet tablo (etiket => değer)
     */
    public function queue(string $key, string $to, array $vars, array $action = [], array $details = [], ?string $relatedType = null, ?int $relatedId = null): EmailLog
    {
        $businessId = $this->tenant->businessId();
        $business = Business::with('modules')->findOrFail($businessId);
        $template = EmailTemplate::where('key', $key)->first();
        $default = EmailTemplates::DEFAULTS[$key];
        $vars += ['isletme' => $business->display_name];

        $configured = EmailSetting::where('status', '!=', 'failed')->exists() && $business->moduleEnabled('email');

        $log = EmailLog::create([
            'template_key' => $key,
            'to_email' => $to,
            'subject' => Str::limit(EmailTemplates::fill($template?->subject ?? $default['subject'], $vars), 250, ''),
            'payload' => [
                'intro' => EmailTemplates::fill($template?->intro ?? $default['intro'], $vars),
                'action_url' => $action['url'] ?? null,
                'action_label' => $action['label'] ?? null,
                'details' => $details,
            ],
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'status' => $configured ? 'queued' : 'skipped',
            'last_error_code' => $configured ? null : 'not_configured',
        ]);

        if ($configured) {
            DB::afterCommit(fn () => $this->dispatchSafely($businessId, $log->id));
        }

        return $log;
    }

    /**
     * Kuyruk senkron çalışsa bile (QUEUE_CONNECTION=sync) gönderim hatası isteği bozmaz;
     * durum email_logs'a zaten yazılmıştır.
     */
    private function dispatchSafely(int $businessId, int $logId): void
    {
        try {
            SendBusinessEmail::dispatch($businessId, $logId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Beklemeden (kuyruğa almadan) gönderir: giriş doğrulama kodu gibi kişinin o anda beklediği e-postalar için.
     * $code e-postada büyük kutuda gösterilir ama email_logs'a yazılmaz (kayıtta yalnızca gönderildiği görünür).
     * Yerel geliştirmede e-posta ayarı yoksa kod log dosyasına yazılır.
     *
     * @param  array<string, scalar|null>  $vars
     */
    public function sendNow(string $key, string $to, array $vars, ?string $code = null): bool
    {
        $businessId = $this->tenant->businessId();
        $business = Business::with('modules')->findOrFail($businessId);
        $template = EmailTemplate::where('key', $key)->first();
        $default = EmailTemplates::DEFAULTS[$key];
        $vars += ['isletme' => $business->display_name];
        $subject = Str::limit(EmailTemplates::fill($template?->subject ?? $default['subject'], $vars), 250, '');
        $intro = EmailTemplates::fill($template?->intro ?? $default['intro'], $vars);
        $setting = $business->moduleEnabled('email') ? EmailSetting::where('status', '!=', 'failed')->first() : null;

        $log = EmailLog::create(['template_key' => $key, 'to_email' => $to, 'subject' => $subject, 'payload' => ['intro' => $intro],
            'status' => $setting ? 'queued' : 'skipped', 'last_error_code' => $setting ? null : 'not_configured']);

        if (! $setting) {
            if (app()->environment('local')) {
                Log::channel('single')->info("[business-mail] {$to} · {$subject}", ['code' => $code]);

                return true;
            }

            return false;
        }

        $log->increment('attempts');
        $body = app(BusinessEmailRenderer::class)->render($business, $key, $subject, ['intro' => $intro, 'code' => $code]);
        try {
            app(MailTransport::class)->send(
                ['email' => $setting->from_email, 'name' => $setting->from_name ?: $business->display_name, 'password' => app(SecretBox::class)->decrypt($setting->app_password_encrypted)],
                ['to' => $to, 'subject' => $subject] + $body,
            );
        } catch (MailFailure $e) {
            $log->update(['status' => 'failed', 'last_error_code' => $e->reason]);
            if ($e->reason === 'auth') {
                $setting->update(['status' => 'failed', 'last_error_code' => 'auth']);
            }

            return false;
        }
        $log->update(['status' => 'sent', 'sent_at' => now()]);

        return true;
    }

    /** Başarısız e-postayı yeniden kuyruğa alır. */
    public function retry(EmailLog $log): void
    {
        $log->update(['status' => 'queued', 'last_error_code' => null]);
        $this->dispatchSafely($log->business_id, $log->id);
    }
}
