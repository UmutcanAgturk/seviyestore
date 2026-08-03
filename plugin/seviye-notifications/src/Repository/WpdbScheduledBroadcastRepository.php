<?php

declare(strict_types=1);

namespace Seviye\Notifications\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Domain\ScheduledBroadcast;
use Seviye\Notifications\Domain\ScheduledBroadcastStatus;

final class WpdbScheduledBroadcastRepository implements ScheduledBroadcastRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(
        int $createdByUserId,
        ?int $branchId,
        string $subject,
        string $body,
        array $channels,
        string $scheduledAt
    ): ScheduledBroadcast {
        $now = $this->now();

        $this->connection->insert($this->connection->table('scheduled_broadcasts'), [
            'created_by' => $createdByUserId,
            'branch_id' => $branchId,
            'subject' => $subject,
            'body' => $body,
            'channels' => $this->encodeChannels($channels),
            'scheduled_at' => $scheduledAt,
            'status' => ScheduledBroadcastStatus::PENDING->value,
            'created_at' => $now,
        ]);

        $broadcast = $this->find($this->connection->lastInsertId());

        if ($broadcast === null) {
            throw new RuntimeException('Zamanlanmış duyuru eklendikten sonra okunamadı.');
        }

        return $broadcast;
    }

    public function find(int $id): ?ScheduledBroadcast
    {
        $table = $this->connection->table('scheduled_broadcasts');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function allForBranch(?int $branchId): array
    {
        $table = $this->connection->table('scheduled_broadcasts');

        if ($branchId === null) {
            $sql = "SELECT * FROM {$table} ORDER BY scheduled_at ASC, id ASC";

            return array_map($this->hydrate(...), $this->connection->getResults($sql));
        }

        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE branch_id = %d ORDER BY scheduled_at ASC, id ASC",
            [$branchId]
        );

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    public function markSent(int $id): void
    {
        $table = $this->connection->table('scheduled_broadcasts');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, sent_at = %s WHERE id = %d",
            [ScheduledBroadcastStatus::SENT->value, $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    public function cancel(int $id): void
    {
        $table = $this->connection->table('scheduled_broadcasts');
        $sql = $this->connection->prepare(
            'UPDATE ' . $table . ' SET status = %s WHERE id = %d',
            [ScheduledBroadcastStatus::CANCELLED->value, $id]
        );

        $this->connection->query($sql);
    }

    /**
     * @param list<NotificationChannel> $channels
     */
    private function encodeChannels(array $channels): string
    {
        return implode(',', array_map(static fn (NotificationChannel $channel): string => $channel->value, $channels));
    }

    /**
     * @return list<NotificationChannel>
     */
    private function decodeChannels(string $raw): array
    {
        $channels = [];

        foreach (explode(',', $raw) as $value) {
            $channel = NotificationChannel::tryFrom(trim($value));

            if ($channel !== null) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ScheduledBroadcast
    {
        $branchId = isset($row['branch_id']) ? (int) $row['branch_id'] : 0;

        return new ScheduledBroadcast(
            (int) $row['id'],
            (int) $row['created_by'],
            $branchId > 0 ? $branchId : null,
            (string) $row['subject'],
            (string) $row['body'],
            $this->decodeChannels((string) $row['channels']),
            (string) $row['scheduled_at'],
            ScheduledBroadcastStatus::from((string) $row['status']),
            (string) $row['created_at']
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
