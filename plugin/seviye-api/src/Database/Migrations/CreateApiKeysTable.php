<?php

declare(strict_types=1);

namespace Seviye\Api\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_api_keys. `user_id` is intentionally NOT a foreign key to
 * wp_users - the same reasoning as Security's `scp_user_identities.user_id`
 * (see CreateUserIdentitiesTable): WordPress core does not guarantee a
 * stable storage engine/charset for its own tables.
 *
 * `key_hash` is UNIQUE (also the only column an authentication lookup ever
 * queries by - see Support\ApiKeyGenerator's docblock for why a plain
 * SHA-256 digest, not a salted password hash, is correct here) - a
 * collision would mean two different plain keys hash identically, which
 * `random_bytes(24)`'s 192 bits of entropy makes practically impossible;
 * the constraint exists as a defensive guarantee, not because collisions
 * are expected.
 *
 * `revoked_at` makes this a soft-delete: a revoked key's row is kept (who
 * held it, when it was revoked) rather than deleted, the same audit-trail
 * reasoning as every other "status" column in this platform (see
 * Notifications\Domain\NotificationStatus).
 */
final class CreateApiKeysTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_30_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_api_keys table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('api_keys');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(100) NOT NULL,
            key_prefix VARCHAR(20) NOT NULL,
            key_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            revoked_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY key_hash (key_hash),
            KEY user_id (user_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('api_keys'));
    }
}
