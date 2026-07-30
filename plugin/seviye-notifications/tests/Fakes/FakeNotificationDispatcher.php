<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Fakes;

use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;

final class FakeNotificationDispatcher implements NotificationDispatcherInterface
{
    /** @var list<array{userId: int, channel: NotificationChannel, eventName: string, subject: string, body: string}> */
    public array $calls = [];

    public function dispatch(
        int $userId,
        NotificationChannel $channel,
        string $eventName,
        string $subject,
        string $body
    ): void {
        $this->calls[] = [
            'userId' => $userId,
            'channel' => $channel,
            'eventName' => $eventName,
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
