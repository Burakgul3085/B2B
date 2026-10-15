<?php

namespace App\Domain\Mail;

use RuntimeException;

/**
 * Gönderim hatası. Yalnızca kısa bir kod taşır; sunucunun ham yanıtı ve kimlik bilgileri bilerek saklanmaz.
 */
class MailFailure extends RuntimeException
{
    public const MESSAGES = [
        'auth' => 'Google uygulama şifresini kabul etmedi. E-posta adresini ve 16 haneli uygulama şifresini kontrol edin; Google hesabında 2 adımlı doğrulamanın açık olduğundan emin olun.',
        'connect' => 'Gmail sunucusuna bağlanılamadı. Sunucunun internet bağlantısını veya güvenlik duvarını (587 numaralı port) kontrol edin.',
        'recipient' => 'Alıcı adresi Gmail tarafından reddedildi.',
        'sender' => 'Gönderen adres reddedildi. Gönderen adres, uygulama şifresinin ait olduğu Google hesabı olmalıdır.',
        'not_configured' => 'İşletmenin e-posta ayarı yapılmamış.',
        'unknown' => 'E-posta gönderilemedi. Bir süre sonra tekrar deneyin.',
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct(self::MESSAGES[$reason] ?? self::MESSAGES['unknown']);
    }

    /** Kimlik doğrulama hatası tekrar denemekle düzelmez. */
    public function isPermanent(): bool
    {
        return in_array($this->reason, ['auth', 'sender', 'recipient', 'not_configured'], true);
    }
}
