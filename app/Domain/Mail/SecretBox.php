<?php

namespace App\Domain\Mail;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * İşletme Gmail uygulama şifrelerinin şifrelenmesi. APP_KEY'den ayrı bir anahtar (MAIL_SECRET_KEY) ve
 * kimlik doğrulamalı şifreleme (AES-256-GCM) kullanılır. Düz şifre veritabanına, loga veya ekrana yazılmaz.
 */
class SecretBox
{
    private ?Encrypter $encrypter = null;

    public function encrypt(string $plain): string
    {
        return $this->encrypter()->encryptString($plain);
    }

    public function decrypt(string $cipher): string
    {
        return $this->encrypter()->decryptString($cipher);
    }

    private function encrypter(): Encrypter
    {
        if ($this->encrypter) {
            return $this->encrypter;
        }

        $key = (string) config('b2b.mail_secret_key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        if (strlen($key) !== 32) {
            throw new RuntimeException('MAIL_SECRET_KEY tanımlı değil veya 32 bayt değil.');
        }

        return $this->encrypter = new Encrypter($key, 'aes-256-gcm');
    }
}
