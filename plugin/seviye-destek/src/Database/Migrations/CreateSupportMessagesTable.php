<?php

declare(strict_types=1);

namespace Seviye\Destek\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_support_messages: the thread of messages under one ticket
 * (opening message + every reply, veli or personel). FK to
 * scp_support_tickets(id) ON DELETE CASCADE - both tables belong to this
 * same plugin, so unlike branch_id above, this is an internal scp_*-to-
 * scp_* reference and a real FOREIGN KEY is the platform's convention here
 * (see plugin/seviye-depo/src/Database/Migrations/CreatePurchaseOrderItemsTable.php).
 *
 * The FK is added via ForeignKeyInstaller AFTER dbDelta(), not inline in
 * the CREATE TABLE - confirmed against a real WordPress/MariaDB install
 * that dbDelta() cannot reliably diff a FOREIGN KEY clause written inside
 * the table body: on every subsequent run it misreads that line as a
 * missing COLUMN and emits an invalid
 * `ALTER TABLE ... ADD COLUMN FOREIGN KEY (...)`, which MariaDB rejects
 * (logged as a WordPress database error on every page load). Every other
 * FK'd migration in this codebase already followed the two-step pattern;
 * this one was the sole exception.
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
            KEY ticket_id (ticket_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_ticket_id_fk',
            "FOREIGN KEY (ticket_id) REFERENCES {$ticketsTable} (id) ON DELETE CASCADE"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('support_messages'));
    }
}
