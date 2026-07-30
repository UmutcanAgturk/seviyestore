<?php

declare(strict_types=1);

namespace Seviye\Notifications\Domain;

/**
 * One notification, recorded before an actual delivery attempt is made
 * ({@see \Seviye\Notifications\Repository\NotificationRepositoryInterface::record()}
 * inserts as PENDING) and then updated in place to SENT/FAILED - a
 * deliberate exception to the platform's usual "ledger, never UPDATE"
 * principle (see docs/ARCHITECTURE.md, "Test stratejisi"'nden önceki
 * bölümler): a notification's delivery status is live, mutable state (like
 * Security's `scp_two_factor_secrets.confirmed_at`), not financial history.
 *
 * `eventName` records which platform event produced this notification (e.g.
 * `security.password_reset_requested`) - purely informational/audit, no
 * code branches on it after the fact.
 *
 * `readAt` is only ever set for {@see NotificationChannel::PANEL} rows; it
 * stays NULL for EMAIL/SMS, which have no "read" concept of their own once
 * delivered.
 */
final class Notification
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly NotificationChannel $channel,
        public readonly string $eventName,
        public readonly string $subject,
        public readonly string $body,
        public readonly NotificationStatus $status,
        public readonly ?string $error,
        public readonly string $createdAt,
        public readonly ?string $sentAt,
        public readonly ?string $readAt
    ) {
    }
}
