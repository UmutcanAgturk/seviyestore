<?php

declare(strict_types=1);

namespace Seviye\Branches\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

final class CreateBranchesTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_28_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_branches table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('branches');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            iban CHAR(34) NULL,
            commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            phone VARCHAR(20) NULL,
            address TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('branches'));
    }
}
