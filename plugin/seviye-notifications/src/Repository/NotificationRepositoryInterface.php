<?php

declare(strict_types=1);

namespace Seviye\Notifications\Repository;

use Seviye\Notifications\Domain\Notification;
use Seviye\Notifications\Domain\NotificationChannel;

interface NotificationRepositoryInterface
{
    /**
     * Inserts a new notification row as PENDING - the actual delivery
     * attempt (a {@see \Seviye\Notifications\Channel\ChannelInterface} call)
     * happens after this returns, and reports back through
     * {@see markSent()}/{@see markFailed()}.
     */
    public function record(
        int $userId,
        NotificationChannel $channel,
        string $eventName,
        string $subject,
        string $body
    ): Notification;

    public function markSent(int $id): void;

    public function markFailed(int $id, string $error): void;

    /**
     * Scoped to $userId - the same "server resolves scope, never trusts the
     * client" rule the rest of the platform applies to any state change
     * touching a specific row: a user can only ever mark their OWN
     * notification read, no exceptions.
     */
    public function markRead(int $id, int $userId): void;

    /**
     * @return list<Notification>
     */
    public function findForUser(int $userId, ?NotificationChannel $channel = null): array;

    public function unreadCountForUser(int $userId): int;
}
