<?php

declare(strict_types=1);

namespace Seviye\Core\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_settings, the platform-wide key/value configuration store
 * shared by Core and every module (in place of scattering wp_options rows).
 */
final class CreateSettingsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_28_000002';
    }

    public function description(): string
    {
        return 'Creates the scp_settings key-value store.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('settings');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(191) NOT NULL,
            setting_value LONGTEXT NULL,
            autoload TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_key (setting_key)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('settings'));
    }
}
