<?php

namespace Tests\Fakes;

use App\Domain\Mail\MailFailure;
use App\Domain\Mail\MailTransport;

/** Testlerde Gmail yerine: gönderilenleri kaydeder, istenirse belirli bir hatayla başarısız olur. */
class FakeMailTransport implements MailTransport
{
    /** @var array<int, array{sender: array, mail: array}> */
    public array $sent = [];

    public array $verified = [];

    public ?string $failWith = null;

    public function send(array $sender, array $mail): void
    {
        if ($this->failWith) {
            throw new MailFailure($this->failWith);
        }
        $this->sent[] = ['sender' => $sender, 'mail' => $mail];
    }

    public function verify(array $sender): void
    {
        if ($this->failWith) {
            throw new MailFailure($this->failWith);
        }
        $this->verified[] = $sender;
    }

    /** @return string[] alıcılar */
    public function recipients(): array
    {
        return array_column(array_column($this->sent, 'mail'), 'to');
    }
}
