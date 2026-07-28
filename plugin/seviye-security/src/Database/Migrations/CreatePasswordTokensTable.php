<?php

declare(strict_types=1);

namespace Seviye\Security\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_password_tokens: single-use tokens backing "Şifremi Unuttum"
 * and "İlk Şifre Oluştur". Only a SHA-256 hash of the token is stored - see
 * {@see \Seviye\Security\Token\PasswordTokenService}.
 */
final class CreatePasswordTokensTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_28_000002';
    }

    public function description(): string
    {
        return 'Creates the scp_password_tokens table for single-use password reset / first-setup tokens.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('password_tokens');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            purpose VARCHAR(20) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('password_tokens'));
    }
}
