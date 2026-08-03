<?php

declare(strict_types=1);

namespace Seviye\Notifications\Repository;

use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Domain\ScheduledBroadcast;

interface ScheduledBroadcastRepositoryInterface
{
    /**
     * @param list<NotificationChannel> $channels
     */
    public function create(
        int $createdByUserId,
        ?int $branchId,
        string $subject,
        string $body,
        array $channels,
        string $scheduledAt
    ): ScheduledBroadcast;

    public function find(int $id): ?ScheduledBroadcast;

    /**
     * $branchId null = every scheduled broadcast, branch-scoped or not
     * (Genel Merkez/Bölge Müdürü); non-null = only that branch's own
     * (Şube Müdürü) - same convention as
     * Seviye\Destek\Repository\SupportTicketRepositoryInterface::allForBranch().
     *
     * @return list<ScheduledBroadcast> soonest-scheduled first
     */
    public function allForBranch(?int $branchId): array;

    public function markSent(int $id): void;

    public function cancel(int $id): void;
}
