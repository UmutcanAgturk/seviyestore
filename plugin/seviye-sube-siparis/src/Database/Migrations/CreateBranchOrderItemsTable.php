<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_branch_order_items. branch_order_id ON DELETE CASCADE (bir
 * siparişin kalemleri o siparişten bağımsız anlamlı değil -
 * scp_purchase_order_items ile aynı ilke). product_id'de FK YOK
 * (WooCommerce'in kendi ürün id'si, wp_posts).
 *
 * free_quantity_applied/paid_quantity/unit_price varsayılan olarak 0/0/NULL
 * - Genel Merkez onaylayana kadar (approve()) anlamsızdır, yalnızca o anda
 * {@see \Seviye\SubeSiparis\Support\BranchOrderSplitCalculator} tarafından
 * hesaplanıp yazılır ve bir daha değişmez (bkz. Domain\BranchOrderItem'ın
 * kendi docblock'u).
 */
final class CreateBranchOrderItemsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_12_000003';
    }

    public function description(): string
    {
        return 'Creates the scp_branch_order_items table with a FK to scp_branch_orders.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('branch_order_items');
        $branchOrdersTable = $connection->table('branch_orders');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            quantity_requested INT UNSIGNED NOT NULL,
            free_quantity_applied INT UNSIGNED NOT NULL DEFAULT 0,
            paid_quantity INT UNSIGNED NOT NULL DEFAULT 0,
            unit_price DECIMAL(10,2) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY branch_order_id (branch_order_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_branch_order_id_fk',
            "FOREIGN KEY (branch_order_id) REFERENCES {$branchOrdersTable} (id) ON DELETE CASCADE"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('branch_order_items'));
    }
}
