<?php

declare(strict_types=1);

namespace Seviye\Parents\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_parent_profiles: one row per Veli WP user, for fields
 * wp_users has no room for. No FK to wp_users - see
 * plugin/seviye-security/src/Database/Migrations/CreateUserIdentitiesTable.php
 * for the platform-wide reasoning.
 */
final class CreateParentProfilesTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_28_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_parent_profiles table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('parent_profiles');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            phone VARCHAR(20) NULL,
            notification_preference VARCHAR(10) NOT NULL DEFAULT 'email',
            kvkk_consent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('parent_profiles'));
    }
}
