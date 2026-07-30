<?php

declare(strict_types=1);

namespace Seviye\Security\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_two_factor_secrets. Unlike scp_password_tokens (single-use,
 * deleted on redemption) this is live, mutable credential state - the same
 * kind of thing a password hash is - so, unlike the financial ledgers in
 * Seviye Finance, updating a row in place here is correct, not a violation
 * of any immutability principle.
 *
 * `secret_encrypted` is never stored in plaintext - see
 * {@see \Seviye\Security\TwoFactor\Encryptor}. `confirmed_at` is NULL while
 * a setup is in progress (secret generated, not yet verified with a code)
 * and set once {@see \Seviye\Security\TwoFactor\TwoFactorService::confirmSetup()}
 * succeeds; {@see \Seviye\Security\Auth\AuthService}'s login flow only
 * treats a row as "2FA enabled" once confirmed_at is non-null, so an
 * abandoned setup never accidentally locks a user out.
 */
final class CreateTwoFactorSecretsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_29_000003';
    }

    public function description(): string
    {
        return 'Creates the scp_two_factor_secrets table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('two_factor_secrets');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            secret_encrypted TEXT NOT NULL,
            confirmed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('two_factor_secrets'));
    }
}
