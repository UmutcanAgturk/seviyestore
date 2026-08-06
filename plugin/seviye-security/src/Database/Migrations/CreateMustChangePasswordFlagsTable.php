<?php

declare(strict_types=1);

namespace Seviye\Security\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_must_change_password_flags - the existence of a row for a
 * user_id means that user's CURRENT password was set by someone else (HQ/
 * branch staff creating or resetting an account via
 * {@see \Seviye\Security\Http\Admin\UserListPage}, or a veli account
 * auto-created by Students with a generated password - see
 * `students.parent_password_generated`, listened for in
 * SecurityModule::boot()), never chosen by the account holder themselves -
 * so they must set their own on their next successful login before
 * reaching the dashboard. Cleared the moment they do (via
 * {@see \Seviye\Security\Http\AuthRestController::completeFirstLogin()}),
 * or via any other path that lets them choose their own password (the
 * token-based "Şifremi Unuttum" redemption, or the logged-in "Profilim"
 * self-service change) - see those call sites.
 *
 * A dedicated table rather than WP user meta, same "Kural" as every other
 * per-user Security concern in this plugin (identity, 2FA secret, password
 * tokens) - see docs/ARCHITECTURE.md. Not a foreign key to wp_users, same
 * reasoning as {@see CreateUserIdentitiesTable}.
 */
final class CreateMustChangePasswordFlagsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_06_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_must_change_password_flags table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('must_change_password_flags');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            user_id BIGINT UNSIGNED NOT NULL,
            flagged_at DATETIME NOT NULL,
            PRIMARY KEY  (user_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('must_change_password_flags'));
    }
}
