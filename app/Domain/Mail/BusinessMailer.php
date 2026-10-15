<?php

namespace App\Domain\Mail;

use App\Domain\Tenancy\TenantContext;
use App\Jobs\SendBusinessEmail;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
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
            DB::afterCommit(fn () => SendBusinessEmail::dispatch($businessId, $log->id));
        }

        return $log;
    }

    /** Başarısız e-postayı yeniden kuyruğa alır. */
    public function retry(EmailLog $log): void
    {
        $log->update(['status' => 'queued', 'last_error_code' => null]);
        SendBusinessEmail::dispatch($log->business_id, $log->id);
    }
}
