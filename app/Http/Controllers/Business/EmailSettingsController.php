<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Mail\BusinessMailer;
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
use Illuminate\Support\Str;
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

    public function update(Request $request): RedirectResponse
    {
        $setting = EmailSetting::first();
        $request->merge(['app_password' => preg_replace('/\s+/', '', (string) $request->input('app_password'))]);

        $data = $request->validate([
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:120'],
            // Google uygulama şifresi 16 harftir (boşluksuz). Kayıtlıysa boş bırakılabilir.
            'app_password' => [$setting ? 'nullable' : 'required', 'regex:/^[a-zA-Z]{16}$/'],
            'payment_reminders' => ['boolean'],
        ], ['app_password.regex' => 'Uygulama şifresi 16 harften oluşmalıdır (Google gösterdiği boşluklar olmadan da girebilirsiniz).'],
            ['from_email' => 'gönderen e-posta', 'from_name' => 'görünen ad', 'app_password' => 'uygulama şifresi']);

        $values = [
            'from_email' => Str::lower($data['from_email']),
            'from_name' => $data['from_name'] ?? null,
            'payment_reminders' => $request->boolean('payment_reminders'),
        ];

        $credentialsChanged = ! $setting || filled($data['app_password'] ?? null) || $setting->from_email !== $values['from_email'];
        if (filled($data['app_password'] ?? null)) {
            $values['app_password_encrypted'] = $this->secrets->encrypt($data['app_password']);
        }
        if ($credentialsChanged) {
            $values += ['status' => 'unverified', 'last_error_code' => null];
        }

        $setting ? $setting->update($values) : EmailSetting::create($values);
        $this->audit->log('email.settings_updated', null, [], ['from_email' => $values['from_email'], 'password_changed' => filled($data['app_password'] ?? null)]);

        return back()->with('status', $credentialsChanged ? 'E-posta ayarı kaydedildi. Şimdi “Bağlantıyı test et” ile doğrulayın.' : 'E-posta ayarı kaydedildi.');
    }

    /** Gmail SMTP bağlantısı ve kimlik doğrulaması denenir; başarılıysa kullanıcının kendi adresine test e-postası gider. */
    public function test(Request $request, MailTransport $transport): RedirectResponse
    {
        $setting = EmailSetting::firstOrFail();
        $key = 'email-test:'.$setting->business_id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Çok fazla deneme. Lütfen birkaç dakika sonra tekrar deneyin.']);
        }
        RateLimiter::hit($key, 600);

        $sender = ['email' => $setting->from_email, 'name' => $setting->from_name, 'password' => $this->secrets->decrypt($setting->app_password_encrypted)];

        try {
            $transport->verify($sender);
            $transport->send($sender, [
                'to' => $request->user()->email,
                'subject' => 'Test e-postası — '.$request->user()->business->display_name,
                'html' => view('emails.business', [
                    'business' => $request->user()->business, 'subject' => 'E-posta ayarınız çalışıyor',
                    'intro' => 'Bu bir test e-postasıdır. Bayilerinize gönderilecek bildirimler bu hesaptan gidecek.', 'details' => [], 'action_url' => null,
                ])->render(),
                'text' => 'E-posta ayarınız çalışıyor.',
            ]);
        } catch (MailFailure $e) {
            $setting->update(['status' => 'failed', 'last_tested_at' => now(), 'last_error_code' => $e->reason]);
            $this->audit->log('email.test_failed', null, [], ['reason' => $e->reason]);

            return back()->withErrors(['email' => $e->getMessage()]);
        }

        $setting->update(['status' => 'verified', 'last_tested_at' => now(), 'last_error_code' => null]);
        $this->audit->log('email.test_succeeded');

        return back()->with('status', 'Bağlantı başarılı. '.$request->user()->email.' adresine test e-postası gönderildi.');
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
