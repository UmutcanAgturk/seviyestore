<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Fakes;

use Seviye\Notifications\Channel\ChannelInterface;

final class FakeChannel implements ChannelInterface
{
    /** @var list<array{recipient: string, subject: string, body: string}> */
    public array $sent = [];

    public function __construct(private readonly bool $succeeds = true)
    {
    }

    public function send(string $recipient, string $subject, string $body): bool
    {
        $this->sent[] = ['recipient' => $recipient, 'subject' => $subject, 'body' => $body];

        return $this->succeeds;
    }
}
