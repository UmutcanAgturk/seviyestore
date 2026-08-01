<?php

declare(strict_types=1);

namespace Seviye\Depo\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_purchase_order_items. purchase_order_id ON DELETE CASCADE
 * (bir siparişin kalemleri o siparişten bağımsız anlamlı değil - scp_
 * price_rules'ın branch_id/student_id CASCADE'iyle aynı ilke).
 * product_id'de FK YOK - WooCommerce'in kendi ürün/varyasyon id'sine
 * işaret eder (wp_posts), bu platform WP/WC çekirdek tablolarına FK
 * koymaz (bkz. docs/ARCHITECTURE.md, "Kural").
 */
final class CreatePurchaseOrderItemsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_01_000004';
    }

    public function description(): string
    {
        return 'Creates the scp_purchase_order_items table with a FK to scp_purchase_orders.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('purchase_order_items');
        $purchaseOrdersTable = $connection->table('purchase_orders');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            purchase_order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            quantity_ordered INT UNSIGNED NOT NULL,
            quantity_received INT UNSIGNED NOT NULL DEFAULT 0,
            unit_cost DECIMAL(10,2) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY purchase_order_id (purchase_order_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_purchase_order_id_fk',
            "FOREIGN KEY (purchase_order_id) REFERENCES {$purchaseOrdersTable} (id) ON DELETE CASCADE"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('purchase_order_items'));
    }
}
