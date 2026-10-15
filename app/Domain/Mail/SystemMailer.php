<?php

namespace App\Domain\Mail;

use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Platformun kendi hesap güvenliği e-postaları (davet, aktivasyon, parola sıfırlama).
 * Toptancının bayilerine gönderdiği ticari e-postalar işletme bazlı göndericiyle gider (Aşama 7).
 */
class SystemMailer
{
    public function isConfigured(): bool
    {
        $c = config('b2b.system_mail');

        return filled($c['host']) && filled($c['username']) && filled($c['password']) && filled($c['from_address']);
    }

    /**
     * @param  array{title: string, intro: string, action_url?: string, action_label?: string, outro?: string}  $content
     */
    public function send(string $to, string $subject, array $content): bool
    {
        $html = view('emails.system', $content + ['subject' => $subject])->render();

        if (! $this->isConfigured()) {
            // Yalnızca yerel geliştirmede içerik log'a yazılır; üretimde bağlantılar loglanmaz.
            if (app()->environment('local')) {
                Log::channel('single')->info("[system-mail] {$to} · {$subject}", [
                    'action_url' => $content['action_url'] ?? null,
                ]);

                return true;
            }

            return false;
        }

        $c = config('b2b.system_mail');
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $c['host'];
            $mail->Port = $c['port'];
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Username = $c['username'];
            $mail->Password = $c['password'];
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Timeout = 15;
            $mail->setFrom($c['from_address'], $c['from_name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = strip_tags($content['intro'])."\n\n".($content['action_url'] ?? '');

            return $mail->send();
        } catch (MailerException) {
            // Ayrıntı (sunucu yanıtı, kimlik bilgisi) bilerek loglanmaz.
            Log::warning('Sistem e-postası gönderilemedi.', ['to_domain' => substr(strrchr($to, '@') ?: '', 1)]);

            return false;
        }
    }
}
