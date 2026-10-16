<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Mail\BusinessMailer;
use App\Domain\Mail\EmailSettingService;
use App\Domain\Mail\EmailTemplates;
use App\Domain\Mail\MailFailure;
use App\Domain\Mail\MailTransport;
use App\Domain\Mail\SecretBox;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * E-posta ayarları: yalnızca gönderen e-posta, 16 haneli Google uygulama şifresi ve isteğe bağlı görünen ad.
 * SMTP sunucusu, port ve şifreleme sistemde sabittir (smtp.gmail.com:587, STARTTLS).
 * Kayıtlı şifre hiçbir zaman ekrana geri yazılmaz.
 */
class EmailSettingsController extends Controller
{
    public function __construct(private SecretBox $secrets, private AuditLogger $audit) {}

    public function show(): View
    {
        return view('business.email.settings', [
            'setting' => EmailSetting::first(),
            'templates' => EmailTemplate::pluck('subject', 'key'),
            'defaults' => EmailTemplates::DEFAULTS,
            'stats' => EmailLog::where('created_at', '>=', now()->subDays(30))->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function update(Request $request, EmailSettingService $settings): RedirectResponse
    {
        $changed = $settings->save($request->only(['from_email', 'from_name', 'app_password']) + ['payment_reminders' => $request->boolean('payment_reminders')]);

        return back()->with('status', $changed ? 'E-posta ayarı kaydedildi. Şimdi “Bağlantıyı test et” ile doğrulayın.' : 'E-posta ayarı kaydedildi.');
    }

    /** Gmail SMTP bağlantısı ve kimlik doğrulaması denenir; başarılıysa ana adrese test e-postası gider. */
    public function test(Request $request, MailTransport $transport, EmailSettingService $settings): RedirectResponse
    {
        $key = 'email-test:'.$request->user()->business_id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Çok fazla deneme. Lütfen birkaç dakika sonra tekrar deneyin.']);
        }
        RateLimiter::hit($key, 600);
        abort_unless(EmailSetting::exists(), 404);

        try {
            $to = $settings->test($transport);
        } catch (MailFailure $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return back()->with('status', "Bağlantı başarılı. {$to} adresine test e-postası gönderildi.");
    }

    public function updateTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', Rule::in(array_keys(EmailTemplates::DEFAULTS))],
            'subject' => ['required', 'string', 'max:200'],
            'intro' => ['required', 'string', 'max:2000'],
            'reset' => ['nullable', 'boolean'],
        ], [], ['subject' => 'konu', 'intro' => 'metin']);

        if ($request->boolean('reset')) {
            EmailTemplate::where('key', $data['key'])->delete();

            return back()->with('status', 'Şablon varsayılana döndürüldü.');
        }

        EmailTemplate::updateOrCreate(['key' => $data['key']], ['subject' => $data['subject'], 'intro' => $data['intro']]);

        return back()->with('status', 'Şablon kaydedildi.');
    }

    public function logs(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(array_keys(EmailLog::STATUSES))]])['status'] ?? null;

        return view('business.email.logs', [
            'logs' => EmailLog::when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(40)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function retry(EmailLog $log, BusinessMailer $mailer): RedirectResponse
    {
        abort_unless(in_array($log->status, ['failed', 'skipped'], true), 409);
        if (! EmailSetting::where('status', '!=', 'failed')->exists()) {
            return back()->withErrors(['email' => 'Önce e-posta ayarınızı kaydedip bağlantıyı test edin.']);
        }

        $mailer->retry($log);

        return back()->with('status', 'E-posta yeniden kuyruğa alındı.');
    }
}
