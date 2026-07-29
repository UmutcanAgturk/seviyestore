<?php

declare(strict_types=1);

namespace Seviye\Commerce\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_order_line_items, with real FKs to scp_students and
 * scp_branches - deliberately RESTRICT (the default, unlike
 * scp_price_rules' ON DELETE CASCADE): this table is order/financial
 * history, not a management rule a branch/student owns outright. Silently
 * losing hakediş-relevant records because a student or branch was later
 * deleted is exactly the kind of irreversible data loss this platform
 * avoids by design (same reasoning as scp_students.branch_id's RESTRICT).
 * order_id/order_item_id have no FK: they point at WooCommerce's own order
 * tables, and this platform never puts FKs on WordPress/WooCommerce core
 * tables.
 */
final class CreateOrderLineItemsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_29_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_order_line_items table with FKs to scp_students and scp_branches.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('order_line_items');
        $studentsTable = $connection->table('students');
        $branchesTable = $connection->table('branches');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,
            commission_rate DECIMAL(5,2) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_item (order_id, order_item_id),
            KEY student_id (student_id),
            KEY branch_id (branch_id),
            KEY status (status)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_student_id_fk',
            "FOREIGN KEY (student_id) REFERENCES {$studentsTable} (id)"
        );

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_branch_id_fk',
            "FOREIGN KEY (branch_id) REFERENCES {$branchesTable} (id)"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('order_line_items'));
    }
}
