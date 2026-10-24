<?php

namespace App\Domain\Mail;

use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Platformun kendi hesap güvenliği e-postaları (davet, aktivasyon, parola sıfırlama).
 * Toptancının bayilerine gönderdiği ticari e-postalar işletme bazlı göndericiyle gider (Aşama 7).
 * Alt sınıflar smtp() ile başka bir hesap kullanabilir (ör. mağazanın kendi e-postası).
 */
class SystemMailer
{
    public function isConfigured(): bool
    {
        return $this->smtp() !== null;
    }

    /**
     * Kullanılacak SMTP hesabı; eksikse null.
     *
     * @return array{host: string, port: int, encryption: string, username: string, password: string, from_address: string, from_name: string}|null
     */
    protected function smtp(): ?array
    {
        $c = config('b2b.system_mail');
        if (! filled($c['host']) || ! filled($c['username']) || ! filled($c['password']) || ! filled($c['from_address'])) {
            return null;
        }

        return ['encryption' => 'tls'] + $c;
    }

    /**
     * @param  array{title: string, intro: string, action_url?: string, action_label?: string, outro?: string}  $content
     */
    public function send(string $to, string $subject, array $content): bool
    {
        $html = view('emails.system', $content + ['subject' => $subject])->render();

        return $this->sendHtml($to, $subject, $html, strip_tags($content['intro'])."\n\n".($content['action_url'] ?? ''), [], $content['action_url'] ?? null);
    }

    /**
     * Hazır HTML gövdeyle gönderir. $headers: ek başlıklar (ör. List-Unsubscribe).
     *
     * @param  array<string, string>  $headers
     */
    public function sendHtml(string $to, string $subject, string $html, string $text, array $headers = [], ?string $logUrl = null): bool
    {
        $c = $this->smtp();
        if ($c === null) {
            // Yalnızca yerel geliştirmede içerik log'a yazılır; üretimde bağlantılar loglanmaz.
            if (app()->environment('local')) {
                Log::channel('single')->info("[system-mail] {$to} · {$subject}", ['action_url' => $logUrl]);

                return true;
            }

            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $c['host'];
            $mail->Port = (int) $c['port'];
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = $c['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Username = $c['username'];
            $mail->Password = $c['password'];
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Timeout = 15;
            $mail->setFrom($c['from_address'], $c['from_name']);
            $mail->addAddress($to);
            foreach ($headers as $name => $value) {
                $mail->addCustomHeader($name, $value);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;

            return $mail->send();
        } catch (MailerException) {
            // Ayrıntı (sunucu yanıtı, kimlik bilgisi) bilerek loglanmaz.
            Log::warning('Sistem e-postası gönderilemedi.', ['to_domain' => substr(strrchr($to, '@') ?: '', 1)]);

            return false;
        }
    }
}
