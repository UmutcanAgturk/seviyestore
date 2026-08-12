<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_branch_orders - şube siparişinin başlığı (durum, onay/red
 * bilgisi, aşan tutar için oluşturulan WooCommerce siparişinin id'si).
 * Kalemleri ayrı bir tabloda (scp_branch_order_items, bkz.
 * CreateBranchOrderItemsTable).
 *
 * branch_id FK'sinde bilinçli olarak ON DELETE CASCADE YOK - InnoDB'nin
 * varsayılanı (RESTRICT) geçerli: geçmiş siparişi olan bir şube sessizce
 * silinemesin (scp_purchase_orders.supplier_id ile aynı ilke).
 *
 * wc_order_id (nullable) - aşan miktar için WooCommerce'in kendisinde
 * oluşturulan gerçek, ödenebilir siparişin id'si (wp_posts/wc_order). FK
 * YOK (WC çekirdek tablosu). NULL demek ya henüz onaylanmadı ya da tamamı
 * ücretsiz kotadan karşılandığı için hiç WooCommerce siparişi
 * oluşturulmadı - bkz. Http\WooCommercePaymentBridge.
 */
final class CreateBranchOrdersTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_12_000002';
    }

    public function description(): string
    {
        return 'Creates the scp_branch_orders table with a FK to scp_branches.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('branch_orders');
        $branchesTable = $connection->table('branches');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_by BIGINT UNSIGNED NOT NULL,
            note TEXT NULL,
            submitted_at DATETIME NULL,
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            rejected_reason TEXT NULL,
            wc_order_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY branch_id (branch_id),
            KEY status (status)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_branch_id_fk',
            "FOREIGN KEY (branch_id) REFERENCES {$branchesTable} (id)"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('branch_orders'));
    }
}
