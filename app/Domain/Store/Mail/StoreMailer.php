<?php

namespace App\Domain\Store\Mail;

use App\Domain\Mail\SystemMailer;
use App\Models\Store\StoreSetting;

/**
 * Mağaza e-postaları (sipariş, bildirim, stok, e-bülten). Mağaza Ayarları → E-posta gönderimi'nde e-posta adresi ve
 * (uygulama) şifresi girildiyse onunla, değilse sistem e-posta hesabıyla gönderilir. Sunucu, port ve şifreleme
 * e-posta adresinin alan adından bulunur (Gmail, Outlook, Yandex…); kendi alan adlı adreslerde Hostinger e-postası
 * varsayılır. Gelişmiş ayarlarda sunucu elle de yazılabilir. Parola şifreli saklanır.
 * Gövde B2B e-postalarıyla aynı kurumsal düzendedir (emails.store, gömülü logo).
 */
class StoreMailer extends SystemMailer
{
    public const ENCRYPTIONS = ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)'];

    /** alan adı => [sunucu, port, şifreleme, sağlayıcı adı] */
    private const PROVIDERS = [
        'gmail.com' => ['smtp.gmail.com', 587, 'tls', 'Gmail'],
        'googlemail.com' => ['smtp.gmail.com', 587, 'tls', 'Gmail'],
        'outlook.com' => ['smtp.office365.com', 587, 'tls', 'Outlook'],
        'hotmail.com' => ['smtp.office365.com', 587, 'tls', 'Outlook'],
        'live.com' => ['smtp.office365.com', 587, 'tls', 'Outlook'],
        'msn.com' => ['smtp.office365.com', 587, 'tls', 'Outlook'],
        'yandex.com' => ['smtp.yandex.com', 465, 'ssl', 'Yandex'],
        'yandex.com.tr' => ['smtp.yandex.com', 465, 'ssl', 'Yandex'],
        'yahoo.com' => ['smtp.mail.yahoo.com', 465, 'ssl', 'Yahoo'],
        'icloud.com' => ['smtp.mail.me.com', 587, 'tls', 'iCloud'],
        'me.com' => ['smtp.mail.me.com', 587, 'tls', 'iCloud'],
    ];

    /** Kendi alan adınızla (ör. bilgi@yapbozkirtasiye.com) Hostinger e-postası */
    private const OWN_DOMAIN = ['smtp.hostinger.com', 465, 'ssl', 'Hostinger e-posta (kendi alan adınız)'];

    /** @return array{0: string, 1: int, 2: string, 3: string} sunucu, port, şifreleme, sağlayıcı */
    public static function detect(?string $email): array
    {
        $domain = mb_strtolower((string) substr(strrchr((string) $email, '@') ?: '', 1));

        return self::PROVIDERS[$domain] ?? self::OWN_DOMAIN;
    }

    /** Mağazanın kendi hesabı tanımlı mı? (e-posta adresi ve şifre yeterli) */
    public static function hasOwnAccount(): bool
    {
        $s = StoreSetting::values();

        return filled($s['mail_username']) && filled($s['mail_password']);
    }

    /**
     * @param  array{title: string, intro: string, rows?: list<array{0: string, 1: string}>, action_url?: string, action_label?: string, outro?: string, eyebrow?: string, tagline?: string, documents?: list<array{title: string, html: string}>}  $content
     */
    public function send(string $to, string $subject, array $content): bool
    {
        $mail = app(StoreEmailRenderer::class)->render($subject, $content);

        return $this->sendHtml($to, $subject, $mail['html'], $mail['text'], [], $content['action_url'] ?? null, $mail['inline']);
    }

    protected function smtp(): ?array
    {
        if (! self::hasOwnAccount()) {
            return parent::smtp();
        }
        $s = StoreSetting::values();
        [$host, $port, $encryption] = filled($s['mail_host'])
            ? [$s['mail_host'], (int) ($s['mail_port'] ?: 587), $s['mail_encryption'] ?: 'tls']
            : self::detect($s['mail_username']);

        return [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $s['mail_username'],
            'password' => (string) StoreSetting::secret('mail_password'),
            'from_address' => $s['mail_from_address'] ?: $s['mail_username'],
            'from_name' => $s['mail_from_name'] ?: config('store.name'),
        ];
    }
}
