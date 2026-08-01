<?php

declare(strict_types=1);

namespace Seviye\Depo\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_purchase_orders - satın alma siparişinin başlığı (tedarikçi,
 * durum, beklenen tarih). Kalemleri ayrı bir tabloda
 * (scp_purchase_order_items, bkz. CreatePurchaseOrderItemsTable).
 *
 * supplier_id FK'sinde bilinçli olarak ON DELETE CASCADE YOK - InnoDB'nin
 * varsayılanı (RESTRICT) geçerli, scp_students.branch_id ile aynı ilke:
 * aktif/geçmiş satın alma siparişi olan bir tedarikçi sessizce
 * silinemesin. Bkz. WpdbSupplierRepository::delete().
 */
final class CreatePurchaseOrdersTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_01_000003';
    }

    public function description(): string
    {
        return 'Creates the scp_purchase_orders table with a FK to scp_suppliers.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('purchase_orders');
        $suppliersTable = $connection->table('suppliers');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            supplier_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            expected_date DATE NULL,
            note TEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY supplier_id (supplier_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_supplier_id_fk',
            "FOREIGN KEY (supplier_id) REFERENCES {$suppliersTable} (id)"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('purchase_orders'));
    }
}
