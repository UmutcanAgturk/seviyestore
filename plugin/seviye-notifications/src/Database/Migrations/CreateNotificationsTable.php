<?php

declare(strict_types=1);

namespace Seviye\Notifications\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_notifications. `user_id` is intentionally NOT a foreign key to
 * wp_users - the same reasoning as Security's `scp_user_identities.user_id`
 * and Finance's `scp_hakedis_settlements.recorded_by` (see
 * CreateUserIdentitiesTable/CreateHakedisSettlementsTable): WordPress core
 * does not guarantee a stable storage engine/charset for its own tables, so
 * plugins should not constrain against them at the database level. Every
 * writer here only ever inserts a user ID WordPress has already
 * authenticated (get_current_user_id(), or an EventBus payload's user_id
 * that itself originated from one).
 *
 * Unlike the platform's financial ledgers (hakediş entries/settlements),
 * this table IS updated in place (status, sent_at, read_at) - see
 * Domain\Notification for why that is not a violation of the "ledger, never
 * UPDATE" principle here.
 */
final class CreateNotificationsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_30_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_notifications table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('notifications');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            channel VARCHAR(10) NOT NULL,
            event_name VARCHAR(100) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            error TEXT NULL,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            read_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY channel (channel)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('notifications'));
    }
}
