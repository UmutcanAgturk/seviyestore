<?php

declare(strict_types=1);

namespace Seviye\Notifications\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_scheduled_broadcasts: "Zamanlanmış toplu duyuru" - a "Toplu
 * Duyuru" (see Http\BroadcastRestController) recorded for a future
 * scheduled_at instead of sent immediately, fired by a one-shot WP Cron
 * event (Http\ScheduledBroadcastHooks, `wp_schedule_single_event()` - unlike
 * WeeklyDigestHooks' recurring schedule, each row gets its OWN arbitrary
 * timestamp). `branch_id` NULL means "every branch platform-wide" - the
 * same convention CreateSupportTicketsTable's branch_id uses, and, like
 * that table, intentionally NOT a foreign key into scp_branches (scp_*
 * tables never FK into another module's table across a Contract boundary -
 * see docs/ARCHITECTURE.md, "Kural"; branch_id here is only ever set from
 * the current user's own BranchMembershipInterface lookup or a validated
 * request param, same as BroadcastRestController's existing resolveRecipients()).
 * `channels` is a comma-separated list of Domain\NotificationChannel values
 * (e.g. "email,panel") - not a separate join table, since it is always
 * read/written as a whole, never queried by individual channel.
 */
final class CreateScheduledBroadcastsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_03_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_scheduled_broadcasts table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('scheduled_broadcasts');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_by BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            channels VARCHAR(100) NOT NULL,
            scheduled_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY branch_id (branch_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('scheduled_broadcasts'));
    }
}
