<?php

declare(strict_types=1);

namespace Seviye\Security\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_privacy_requests: KVKK veri ihracı/silme talepleri - see
 * {@see \Seviye\Security\Privacy\PrivacyRequest}.
 */
final class CreatePrivacyRequestsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_02_000004';
    }

    public function description(): string
    {
        return 'Creates the scp_privacy_requests table for KVKK data export/deletion requests.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('privacy_requests');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL,
            note TEXT NULL,
            resolution_note TEXT NULL,
            requested_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            resolved_by BIGINT UNSIGNED NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('privacy_requests'));
    }
}
