<?php

declare(strict_types=1);

namespace Seviye\Notifications\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Notifications\Domain\Notification;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Domain\NotificationStatus;

final class WpdbNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function record(
        int $userId,
        NotificationChannel $channel,
        string $eventName,
        string $subject,
        string $body
    ): Notification {
        $this->connection->insert($this->connection->table('notifications'), [
            'user_id' => $userId,
            'channel' => $channel->value,
            'event_name' => $eventName,
            'subject' => $subject,
            'body' => $body,
            'status' => NotificationStatus::PENDING->value,
            'created_at' => $this->now(),
        ]);

        return $this->find($this->connection->lastInsertId());
    }

    public function markSent(int $id): void
    {
        $table = $this->connection->table('notifications');
        $this->connection->query($this->connection->prepare(
            "UPDATE {$table} SET status = %s, sent_at = %s WHERE id = %d",
            [NotificationStatus::SENT->value, $this->now(), $id]
        ));
    }

    public function markFailed(int $id, string $error): void
    {
        $table = $this->connection->table('notifications');
        $this->connection->query($this->connection->prepare(
            "UPDATE {$table} SET status = %s, error = %s WHERE id = %d",
            [NotificationStatus::FAILED->value, $error, $id]
        ));
    }

    public function markRead(int $id, int $userId): void
    {
        $table = $this->connection->table('notifications');
        $this->connection->query($this->connection->prepare(
            "UPDATE {$table} SET read_at = %s WHERE id = %d AND user_id = %d AND read_at IS NULL",
            [$this->now(), $id, $userId]
        ));
    }

    public function findForUser(int $userId, ?NotificationChannel $channel = null): array
    {
        $table = $this->connection->table('notifications');

        if ($channel === null) {
            $sql = $this->connection->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC",
                [$userId]
            );
        } else {
            $sql = $this->connection->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND channel = %s ORDER BY id DESC",
                [$userId, $channel->value]
            );
        }

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    public function unreadCountForUser(int $userId): int
    {
        $table = $this->connection->table('notifications');
        $sql = $this->connection->prepare(
            "SELECT COUNT(*) AS total FROM {$table} WHERE user_id = %d AND channel = %s AND read_at IS NULL",
            [$userId, NotificationChannel::PANEL->value]
        );

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]['total']) ? (int) $rows[0]['total'] : 0;
    }

    private function find(int $id): Notification
    {
        $table = $this->connection->table('notifications');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            throw new RuntimeException('Notification could not be read back after insert.');
        }

        return $this->hydrate($rows[0]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Notification
    {
        return new Notification(
            (int) $row['id'],
            (int) $row['user_id'],
            NotificationChannel::from((string) $row['channel']),
            (string) $row['event_name'],
            (string) $row['subject'],
            (string) $row['body'],
            NotificationStatus::from((string) $row['status']),
            $row['error'] !== null ? (string) $row['error'] : null,
            (string) $row['created_at'],
            $row['sent_at'] !== null ? (string) $row['sent_at'] : null,
            $row['read_at'] !== null ? (string) $row['read_at'] : null
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
