<?php

declare(strict_types=1);

namespace Seviye\Destek\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_support_tickets: one row per "şikayet/soru" ticket a veli
 * opens. branch_id is NULLABLE and intentionally NOT a foreign key into
 * wp_* or scp_branches (scp_* tables never FK into wp_* tables - see
 * plugin/seviye-security/src/Database/Migrations/CreateUserIdentitiesTable.php
 * for the platform-wide reasoning; branch_id specifically is validated
 * against Seviye\Branches\Contracts\BranchLookupInterface at write time
 * instead, see Http\SupportTicketsRestController). NULL means "Genel
 * Merkez'e" - the veli has no branch context to attach (e.g. no linked
 * children yet), so Genel Merkez/Bölge Müdürü triages it.
 */
final class CreateSupportTicketsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_03_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_support_tickets table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('support_tickets');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY branch_id (branch_id),
            KEY created_by (created_by),
            KEY status (status)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('support_tickets'));
    }
}
