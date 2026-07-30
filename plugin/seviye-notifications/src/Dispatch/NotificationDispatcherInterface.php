<?php

declare(strict_types=1);

namespace Seviye\Notifications\Dispatch;

use Seviye\Notifications\Domain\NotificationChannel;

/**
 * The single entry point every EventBus listener in this module calls -
 * listeners never touch {@see \Seviye\Notifications\Repository\NotificationRepositoryInterface}
 * or a {@see \Seviye\Notifications\Channel\ChannelInterface} directly, so
 * they stay a one-line translation from "domain event happened" to "here is
 * the notification to send", fully unit-testable against a fake dispatcher.
 */
interface NotificationDispatcherInterface
{
    public function dispatch(
        int $userId,
        NotificationChannel $channel,
        string $eventName,
        string $subject,
        string $body
    ): void;
}
