<?php

namespace App\Domain\Store\Mail;

use App\Domain\Mail\SystemMailer;
use App\Models\Store\StoreSetting;

/**
 * Mağaza e-postaları (sipariş, bildirim, stok, e-bülten). Mağaza Ayarları → E-posta gönderimi'nde bir SMTP hesabı
 * tanımlıysa onunla, değilse sistem e-posta hesabıyla gönderilir. Parola şifreli saklanır.
 */
class StoreMailer extends SystemMailer
{
    public const ENCRYPTIONS = ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)'];

    /** Mağazanın kendi hesabı tanımlı mı? */
    public static function hasOwnAccount(): bool
    {
        $s = StoreSetting::values();

        return filled($s['mail_host']) && filled($s['mail_username']) && filled($s['mail_password']) && filled($s['mail_from_address']);
    }

    protected function smtp(): ?array
    {
        if (! self::hasOwnAccount()) {
            return parent::smtp();
        }
        $s = StoreSetting::values();

        return [
            'host' => $s['mail_host'],
            'port' => (int) ($s['mail_port'] ?: 587),
            'encryption' => $s['mail_encryption'] ?: 'tls',
            'username' => $s['mail_username'],
            'password' => (string) StoreSetting::secret('mail_password'),
            'from_address' => $s['mail_from_address'],
            'from_name' => $s['mail_from_name'] ?: config('store.name'),
        ];
    }
}
