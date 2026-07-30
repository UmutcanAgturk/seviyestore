<?php

declare(strict_types=1);

namespace Seviye\Notifications\Dispatch;

use Seviye\Notifications\Channel\ChannelInterface;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Recipient\RecipientResolverInterface;
use Seviye\Notifications\Repository\NotificationRepositoryInterface;

/**
 * record → resolve recipient → send → mark-sent/failed, identically for
 * every channel (see Domain\NotificationStatus for why PANEL going through
 * this same flow, rather than being special-cased, is deliberate).
 */
final class NotificationDispatcher implements NotificationDispatcherInterface
{
    /**
     * @param array<string, ChannelInterface> $channels keyed by {@see NotificationChannel::value}
     */
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly RecipientResolverInterface $recipients,
        private readonly array $channels
    ) {
    }

    public function dispatch(
        int $userId,
        NotificationChannel $channel,
        string $eventName,
        string $subject,
        string $body
    ): void {
        $notification = $this->notifications->record($userId, $channel, $eventName, $subject, $body);

        $recipient = $this->recipients->resolve($userId, $channel);

        if ($recipient === null) {
            $this->notifications->markFailed($notification->id, 'No recipient address for this channel.');

            return;
        }

        $transport = $this->channels[$channel->value] ?? null;

        if ($transport === null) {
            $this->notifications->markFailed($notification->id, 'No channel adapter configured.');

            return;
        }

        if ($transport->send($recipient, $subject, $body)) {
            $this->notifications->markSent($notification->id);

            return;
        }

        $this->notifications->markFailed($notification->id, 'Channel transport reported delivery failure.');
    }
}
