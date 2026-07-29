<?php

declare(strict_types=1);

namespace Seviye\Finance\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_hakedis_entries: an immutable, append-only ledger, so there
 * is deliberately no `updated_at` column (see Domain\HakedisEntry). Real
 * FKs to scp_branches/scp_students, RESTRICT (the default, same reasoning
 * as scp_order_line_items - this is financial history, losing it because a
 * branch/student was later deleted would be exactly the kind of
 * irreversible data loss this platform avoids by design).
 *
 * The UNIQUE (order_id, order_item_id, type) constraint is an idempotency
 * guard: if `commerce.order_line_item_completed`/`_reversed` were ever
 * dispatched twice for the same order item (WooCommerce is expected not to
 * do this, but a ledger is exactly the wrong place to merely assume that),
 * a second INSERT attempt fails instead of silently double-crediting a
 * branch.
 *
 * vat_amount snapshots Commerce's own vat_amount (Seviye\Commerce\Domain\
 * OrderLineItem, itself WooCommerce's tax calculation) - added alongside
 * amount/price rather than in a separate migration, since this table has
 * never been deployed to a live install yet (see root README.md's
 * "headless session" note).
 */
final class CreateHakedisEntriesTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_29_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_hakedis_entries table with FKs to scp_branches and scp_students.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('hakedis_entries');
        $branchesTable = $connection->table('branches');
        $studentsTable = $connection->table('students');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            commission_rate DECIMAL(5,2) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            vat_amount DECIMAL(10,2) NOT NULL,
            type VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_item_type (order_id, order_item_id, type),
            KEY branch_id (branch_id),
            KEY student_id (student_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_branch_id_fk',
            "FOREIGN KEY (branch_id) REFERENCES {$branchesTable} (id)"
        );

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_student_id_fk',
            "FOREIGN KEY (student_id) REFERENCES {$studentsTable} (id)"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('hakedis_entries'));
    }
}
