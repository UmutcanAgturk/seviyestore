<?php

declare(strict_types=1);

namespace Seviye\Pricing\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_price_rules, with real FKs to scp_students and scp_branches
 * (both ON DELETE CASCADE - a rule scoped to a deleted student/branch is
 * meaningless, unlike scp_students.branch_id's RESTRICT: deleting one price
 * rule row is not the high-blast-radius data loss that silently deleting a
 * whole branch's students would be). product_id has no FK: it refers to a
 * WooCommerce product (wp_posts.ID), and this platform never puts FKs on
 * WordPress core tables - see docs/database/README.md.
 */
final class CreatePriceRulesTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_29_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_price_rules table with FKs to scp_students and scp_branches.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('price_rules');
        $studentsTable = $connection->table('students');
        $branchesTable = $connection->table('branches');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NULL,
            branch_id BIGINT UNSIGNED NULL,
            price DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY student_id (student_id),
            KEY branch_id (branch_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_student_id_fk',
            "FOREIGN KEY (student_id) REFERENCES {$studentsTable} (id) ON DELETE CASCADE"
        );

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_branch_id_fk',
            "FOREIGN KEY (branch_id) REFERENCES {$branchesTable} (id) ON DELETE CASCADE"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('price_rules'));
    }
}
