<?php

namespace App\Domain\Mail;

interface MailTransport
{
    /**
     * @param  array{email: string, name: ?string, password: string}  $sender
     * @param  array{to: string, subject: string, html: string, text: string, inline?: list<array{cid: string, data: string, name: string, mime: string}>}  $mail  inline: HTML'de cid: ile kullanılan gömülü görseller
     *
     * @throws MailFailure
     */
    public function send(array $sender, array $mail): void;

    /**
     * Bağlantı ve kimlik doğrulamayı dener; e-posta göndermez.
     *
     * @param  array{email: string, name: ?string, password: string}  $sender
     *
     * @throws MailFailure
     */
    public function verify(array $sender): void;
}
