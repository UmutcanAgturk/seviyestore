<?php

declare(strict_types=1);

namespace Seviye\Commerce\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_product_branches - the shared-catalog visibility toggle
 * ("ürünlerin aktiflik ve pasiflik durumunu kurum kendi öğrencileri ve
 * velileri için düzenleyebilsin"): products themselves stay WooCommerce's
 * own (wp_posts, no FK here - this platform never puts FKs on WordPress
 * core tables, see docs/database/README.md), but a branch may mark a
 * shared product ACTIVE or PASSIVE for its own students/veliler without
 * affecting any other branch.
 *
 * ABSENCE of a row is deliberately "active" (opt-out model, not opt-in): a
 * newly created product is visible to every branch by default, matching a
 * shared catalog where a branch only needs to act when it wants to HIDE
 * something, not to individually turn on every product for every branch.
 * See {@see \Seviye\Commerce\Repository\WpdbProductBranchVisibilityRepository::isActiveForBranch()}.
 *
 * branch_id gets ON DELETE CASCADE (mirrors scp_price_rules): a visibility
 * row scoped to a deleted branch is meaningless, not the kind of
 * high-blast-radius loss scp_students.branch_id's RESTRICT protects
 * against.
 */
final class CreateProductBranchesTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_31_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_product_branches table with a FK to scp_branches.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('product_branches');
        $branchesTable = $connection->table('branches');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY product_branch (product_id, branch_id),
            KEY branch_id (branch_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_branch_id_fk',
            "FOREIGN KEY (branch_id) REFERENCES {$branchesTable} (id) ON DELETE CASCADE"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('product_branches'));
    }
}
