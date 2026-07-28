<?php

declare(strict_types=1);

namespace Seviye\Security\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_user_identities, mapping a T.C. Kimlik No to a WordPress user.
 *
 * Intentionally not a foreign key to wp_users: WordPress core does not
 * guarantee a stable storage engine/charset for its own tables across every
 * install, so plugins should not constrain against them at the database
 * level. Referential integrity is enforced in the application layer instead
 * (callers only link a user ID they have already verified exists).
 */
final class CreateUserIdentitiesTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_28_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_user_identities table mapping T.C. Kimlik No to a WordPress user.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('user_identities');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            tc_no CHAR(11) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tc_no (tc_no),
            UNIQUE KEY user_id (user_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('user_identities'));
    }
}
