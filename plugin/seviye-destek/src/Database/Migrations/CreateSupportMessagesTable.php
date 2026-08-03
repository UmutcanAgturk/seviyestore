<?php

declare(strict_types=1);

namespace Seviye\Destek\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_support_messages: the thread of messages under one ticket
 * (opening message + every reply, veli or personel). FK to
 * scp_support_tickets(id) ON DELETE CASCADE - both tables belong to this
 * same plugin, so unlike branch_id above, this is an internal scp_*-to-
 * scp_* reference and a real FOREIGN KEY is the platform's convention here
 * (see plugin/seviye-depo/src/Database/Migrations/CreatePurchaseOrderItemsTable.php).
 */
final class CreateSupportMessagesTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_03_000002';
    }

    public function description(): string
    {
        return 'Creates the scp_support_messages table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('support_messages');
        $ticketsTable = $connection->table('support_tickets');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT UNSIGNED NOT NULL,
            author_user_id BIGINT UNSIGNED NOT NULL,
            is_staff TINYINT(1) NOT NULL DEFAULT 0,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            FOREIGN KEY (ticket_id) REFERENCES {$ticketsTable} (id) ON DELETE CASCADE
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('support_messages'));
    }
}
