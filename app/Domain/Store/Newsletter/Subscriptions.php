<?php

namespace App\Domain\Store\Newsletter;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Mail\StoreMailer;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreNewsletterSubscriber;
use App\Models\Store\StoreSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * E-bülten aboneliği (6563 sayılı Kanun ve Ticari İletişim Yönetmeliği): açık onay kutusu zorunludur, onay metni,
 * tarih ve IP saklanır; alt bilgiden gelen kayıt e-postadaki bağlantıyla onaylanınca etkinleşir (iki adımlı onay).
 * Her e-postada tek tıkla ayrılma bağlantısı vardır. Onaylar İYS'ye yüklenecek biçimde dışa aktarılır.
 */
class Subscriptions
{
    public function __construct(private StoreMailer $mailer, private AuditLogger $audit) {}

    /** Alt bilgideki form yalnızca açıkken ve onay e-postası gönderilebilirken gösterilir (yerelde e-postalar günlüğe yazılır). */
    public function formAvailable(): bool
    {
        return StoreSetting::flag('newsletter_enabled') && ($this->mailer->isConfigured() || app()->environment('local'));
    }

    /**
     * Alt bilgi formu. Dönüş: pending (onay e-postası gönderildi) | active (zaten abone).
     *
     * @throws ValidationException
     */
    public function subscribe(array $input, ?string $ip): string
    {
        $data = Validator::make($input, [
            'email' => ['required', 'email:rfc', 'max:190'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Abone olmak için ileti onayını işaretleyin.'], ['email' => 'e-posta'])->validate();

        $email = mb_strtolower(trim($data['email']));
        $subscriber = StoreNewsletterSubscriber::firstWhere('email', $email);
        if ($subscriber?->status === 'active') {
            return 'active';
        }

        $subscriber ??= new StoreNewsletterSubscriber(['email' => $email, 'token' => self::token()]);
        $subscriber->fill([
            'status' => 'pending', 'source' => 'footer', 'consent_text' => StoreSetting::get('newsletter_consent'),
            'consent_ip' => $ip, 'consented_at' => now(), 'unsubscribed_at' => null,
            'customer_id' => $subscriber->customer_id ?? StoreCustomer::where('email', $email)->value('id'),
        ])->save();

        $this->sendConfirmation($subscriber);

        return 'pending';
    }

    public function sendConfirmation(StoreNewsletterSubscriber $subscriber): bool
    {
        $name = config('store.name');

        return $this->mailer->send($subscriber->email, "E-bülten aboneliğinizi onaylayın — {$name}", [
            'title' => 'Aboneliğinizi onaylayın',
            'intro' => "{$name} e-bültenine abone olmak için aşağıdaki düğmeye tıklayın. Bu isteği siz yapmadıysanız e-postayı yok sayabilirsiniz; onaylamadığınız sürece size kampanya e-postası gönderilmez.",
            'action_url' => route('store.newsletter.confirm', $subscriber->token),
            'action_label' => 'Aboneliğimi onayla',
        ]);
    }

    /** Onay bağlantısı. Abonelik etkinleşirse aboneyi döndürür. */
    public function confirm(string $token): ?StoreNewsletterSubscriber
    {
        $subscriber = StoreNewsletterSubscriber::firstWhere('token', $token);
        if (! $subscriber || $subscriber->status === 'unsubscribed') {
            return null;
        }
        if ($subscriber->status === 'pending') {
            $subscriber->update(['status' => 'active', 'confirmed_at' => now()]);
        }

        return $subscriber;
    }

    public function unsubscribe(string $token): ?StoreNewsletterSubscriber
    {
        $subscriber = StoreNewsletterSubscriber::firstWhere('token', $token);
        if ($subscriber && $subscriber->status !== 'unsubscribed') {
            $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
            // Üye hesabındaki pazarlama izni de geri alınır (tek yerden yönetilir).
            if ($subscriber->customer_id) {
                StoreCustomer::whereKey($subscriber->customer_id)->update(['marketing_consent_at' => null]);
            }
        }

        return $subscriber;
    }

    /**
     * Üyelik ve hesap ayarlarındaki "kampanyalardan haberdar olmak istiyorum" seçimi aboneliğe yansır.
     * Üye e-postasını hesabıyla doğruladığı için ayrıca onay e-postası gönderilmez.
     */
    public function syncCustomer(StoreCustomer $customer, string $source, ?string $ip = null): void
    {
        $subscriber = StoreNewsletterSubscriber::firstWhere('email', mb_strtolower($customer->email));
        if ($customer->marketing_consent_at) {
            if ($subscriber?->status === 'active') {
                $subscriber->update(['customer_id' => $customer->id]);

                return;
            }
            $subscriber ??= new StoreNewsletterSubscriber(['email' => mb_strtolower($customer->email), 'token' => self::token()]);
            $subscriber->fill([
                'status' => 'active', 'source' => $source, 'customer_id' => $customer->id,
                'consent_text' => 'Kampanya ve indirimlerden e-posta ve SMS ile haberdar olmak istiyorum (üyelik).',
                'consent_ip' => $ip, 'consented_at' => $customer->marketing_consent_at, 'confirmed_at' => now(), 'unsubscribed_at' => null,
            ])->save();
        } elseif ($subscriber && $subscriber->status !== 'unsubscribed') {
            $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        }
    }

    /** Hesap kapatılırken: abonelik kaydı silinir (kişisel veri). */
    public function forget(string $email): void
    {
        StoreNewsletterSubscriber::where('email', mb_strtolower($email))->delete();
    }

    public function delete(StoreNewsletterSubscriber $subscriber): void
    {
        $this->audit->log('store.newsletter_subscriber_deleted', $subscriber, ['status' => $subscriber->status]);
        $subscriber->delete();
    }

    /** Ayarlar: alt bilgideki e-bülten alanı. @throws ValidationException */
    public function saveSettings(array $input): void
    {
        $data = Validator::make($input, [
            'newsletter_title' => ['required', 'string', 'max:80'],
            'newsletter_text' => ['nullable', 'string', 'max:200'],
            'newsletter_consent' => ['required', 'string', 'max:255'],
        ], [], ['newsletter_title' => 'başlık', 'newsletter_text' => 'açıklama', 'newsletter_consent' => 'onay metni'])->validate();

        StoreSetting::put([
            'newsletter_enabled' => filter_var($input['newsletter_enabled'] ?? false, FILTER_VALIDATE_BOOL) ? '1' : '0',
            'newsletter_title' => trim($data['newsletter_title']),
            'newsletter_text' => trim((string) ($data['newsletter_text'] ?? '')) ?: null,
            'newsletter_consent' => trim($data['newsletter_consent']),
        ]);
        $this->audit->log('store.newsletter_settings_updated');
    }

    /**
     * CSV: "list" yönetim listesi; "iys" İYS toplu yükleme biçimi (yalnızca onaylı aboneler ve ayrılanlar).
     */
    public function export(string $format, ?string $status): StreamedResponse
    {
        $query = StoreNewsletterSubscriber::query()->orderBy('id');
        if ($format === 'iys') {
            $query->whereIn('status', ['active', 'unsubscribed']);
        } elseif ($status) {
            $query->where('status', $status);
        }
        $name = ($format === 'iys' ? 'iys-eposta-izinleri-' : 'e-bulten-aboneleri-').now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $format) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel için UTF-8 BOM
            if ($format === 'iys') {
                fputcsv($out, ['ALICI', 'ALICI_TURU', 'IZIN_TURU', 'DURUM', 'IZIN_TARIHI', 'KAYNAK'], ';');
            } else {
                fputcsv($out, ['E-posta', 'Durum', 'Kaynak', 'Onay metni', 'Onay tarihi', 'Onay IP', 'Doğrulama tarihi', 'Ayrılma tarihi'], ';');
            }
            $query->chunk(500, function ($rows) use ($out, $format) {
                foreach ($rows as $s) {
                    $tz = fn ($d) => $d?->timezone('Europe/Istanbul')->format('Y-m-d H:i:s');
                    fputcsv($out, $format === 'iys'
                        ? [$s->email, 'BIREYSEL', 'EPOSTA', $s->status === 'active' ? 'ONAY' : 'RET', $tz($s->status === 'active' ? $s->consented_at : $s->unsubscribed_at), 'HS_WEB']
                        : [$s->email, $s->statusLabel(), StoreNewsletterSubscriber::SOURCES[$s->source] ?? $s->source, $s->consent_text, $tz($s->consented_at), $s->consent_ip, $tz($s->confirmed_at), $tz($s->unsubscribed_at)], ';');
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function token(): string
    {
        return Str::random(48);
    }
}
