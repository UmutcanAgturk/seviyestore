<?php

declare(strict_types=1);

namespace Seviye\Core\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_logs, the centralised audit log table used by
 * {@see \Seviye\Core\Logging\DatabaseLogger} and consumed by every module.
 */
final class CreateLogsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_28_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_logs table used for centralised audit logging.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('logs');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel VARCHAR(64) NOT NULL DEFAULT 'core',
            level VARCHAR(16) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            user_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY channel (channel),
            KEY level (level),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('logs'));
    }
}
