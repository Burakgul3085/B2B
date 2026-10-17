<?php

namespace App\Domain\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Gmail SMTP (PHPMailer). Sunucu, port ve şifreleme kullanıcıdan istenmez; burada sabittir:
 * smtp.gmail.com, 587, STARTTLS, kimlik doğrulama açık, kullanıcı adı = gönderen e-posta.
 */
class GmailTransport implements MailTransport
{
    public const HOST = 'smtp.gmail.com';

    public const PORT = 587;

    public function send(array $sender, array $mail): void
    {
        $mailer = $this->mailer($sender);

        try {
            $mailer->setFrom($sender['email'], $sender['name'] ?? '');
            $mailer->addAddress($mail['to']);
            $mailer->isHTML(true);
            $mailer->Subject = $mail['subject'];
            $mailer->Body = $mail['html'];
            $mailer->AltBody = $mail['text'];
            foreach ($mail['inline'] ?? [] as $image) {
                $mailer->addStringEmbeddedImage($image['data'], $image['cid'], $image['name'], PHPMailer::ENCODING_BASE64, $image['mime']);
            }
            $mailer->send();
        } catch (PHPMailerException) {
            throw new MailFailure($this->classify($mailer->ErrorInfo));
        } finally {
            $mailer->smtpClose();
        }
    }

    public function verify(array $sender): void
    {
        $mailer = $this->mailer($sender);

        try {
            if (! $mailer->smtpConnect()) {
                throw new MailFailure($this->classify($mailer->ErrorInfo));
            }
        } catch (PHPMailerException) {
            throw new MailFailure($this->classify($mailer->ErrorInfo));
        } finally {
            $mailer->smtpClose();
        }
    }

    private function mailer(array $sender): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = self::HOST;
        $mailer->Port = self::PORT;
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->SMTPAuth = true;
        $mailer->Username = $sender['email'];
        $mailer->Password = $sender['password'];
        $mailer->SMTPDebug = SMTP::DEBUG_OFF; // ayrıntılı SMTP diyaloğu (kimlik bilgisi içerebilir) hiçbir yere yazılmaz
        $mailer->Timeout = 15;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;

        return $mailer;
    }

    /** PHPMailer hata metnini yalnızca koda çevirir; metnin kendisi saklanmaz. */
    private function classify(string $error): string
    {
        $e = strtolower($error);

        return match (true) {
            str_contains($e, 'authenticate') || str_contains($e, 'username and password') => 'auth',
            str_contains($e, 'connect') || str_contains($e, 'timed out') => 'connect',
            str_contains($e, 'recipient') => 'recipient',
            str_contains($e, 'from address') || str_contains($e, 'sender') => 'sender',
            default => 'unknown',
        };
    }
}
