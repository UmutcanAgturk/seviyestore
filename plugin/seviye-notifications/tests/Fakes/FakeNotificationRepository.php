<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Fakes;

use Seviye\Notifications\Domain\Notification;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Domain\NotificationStatus;
use Seviye\Notifications\Repository\NotificationRepositoryInterface;

final class FakeNotificationRepository implements NotificationRepositoryInterface
{
    /** @var list<Notification> */
    public array $notifications = [];

    public function record(
        int $userId,
        NotificationChannel $channel,
        string $eventName,
        string $subject,
        string $body
    ): Notification {
        $notification = new Notification(
            count($this->notifications) + 1,
            $userId,
            $channel,
            $eventName,
            $subject,
            $body,
            NotificationStatus::PENDING,
            null,
            '2026-07-30 12:00:00',
            null,
            null
        );

        $this->notifications[] = $notification;

        return $notification;
    }

    public function markSent(int $id): void
    {
        $this->replace($id, static fn (Notification $n): Notification => new Notification(
            $n->id,
            $n->userId,
            $n->channel,
            $n->eventName,
            $n->subject,
            $n->body,
            NotificationStatus::SENT,
            null,
            $n->createdAt,
            '2026-07-30 12:00:01',
            $n->readAt
        ));
    }

    public function markFailed(int $id, string $error): void
    {
        $this->replace($id, static fn (Notification $n): Notification => new Notification(
            $n->id,
            $n->userId,
            $n->channel,
            $n->eventName,
            $n->subject,
            $n->body,
            NotificationStatus::FAILED,
            $error,
            $n->createdAt,
            $n->sentAt,
            $n->readAt
        ));
    }

    public function markRead(int $id, int $userId): void
    {
        $this->replace($id, static function (Notification $n) use ($userId): Notification {
            if ($n->userId !== $userId || $n->readAt !== null) {
                return $n;
            }

            return new Notification(
                $n->id,
                $n->userId,
                $n->channel,
                $n->eventName,
                $n->subject,
                $n->body,
                $n->status,
                $n->error,
                $n->createdAt,
                $n->sentAt,
                '2026-07-30 12:00:02'
            );
        });
    }

    public function findForUser(int $userId, ?NotificationChannel $channel = null): array
    {
        return array_values(array_filter(
            $this->notifications,
            static fn (Notification $n): bool => $n->userId === $userId
                && ($channel === null || $n->channel === $channel)
        ));
    }

    public function unreadCountForUser(int $userId): int
    {
        return count(array_filter(
            $this->notifications,
            static fn (Notification $n): bool => $n->userId === $userId
                && $n->channel === NotificationChannel::PANEL
                && $n->readAt === null
        ));
    }

    /**
     * @param callable(Notification): Notification $replacer
     */
    private function replace(int $id, callable $replacer): void
    {
        foreach ($this->notifications as $index => $notification) {
            if ($notification->id === $id) {
                $this->notifications[$index] = $replacer($notification);

                return;
            }
        }
    }
}
