<?php

declare(strict_types=1);

namespace Seviye\Notifications\Domain;

final class ScheduledBroadcast
{
    /**
     * @param list<NotificationChannel> $channels
     */
    public function __construct(
        public readonly int $id,
        public readonly int $createdByUserId,
        public readonly ?int $branchId,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $channels,
        public readonly string $scheduledAt,
        public readonly ScheduledBroadcastStatus $status,
        public readonly string $createdAt
    ) {
    }
}
