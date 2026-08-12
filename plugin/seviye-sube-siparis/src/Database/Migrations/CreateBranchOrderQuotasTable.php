<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_branch_order_quotas - Genel Merkez'in her (şube, ürün) çifti
 * için belirlediği ücretsiz hak. Satır yoksa o çift için ücretsiz kota 0
 * kabul edilir (bkz. Domain\BranchOrderQuota'nın docblock'u) - "kota
 * tanımlanmamış" ile "kota 0" arasında ayrı bir durum yok, sadeleştirme.
 *
 * product_id'de FK YOK - WooCommerce'in kendi ürün id'sine işaret eder
 * (wp_posts), bu platform WP/WC çekirdek tablolarına FK koymaz (bkz.
 * docs/ARCHITECTURE.md, "Kural").
 *
 * branch_id FK'sinde ON DELETE CASCADE - bir şube silindiğinde onun kota
 * tanımları da anlamsızlaşır (scp_price_rules'ın branch_id CASCADE'iyle
 * aynı ilke).
 */
final class CreateBranchOrderQuotasTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_12_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_branch_order_quotas table with a FK to scp_branches.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('branch_order_quotas');
        $branchesTable = $connection->table('branches');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            free_quantity INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY branch_product (branch_id, product_id)
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
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('branch_order_quotas'));
    }
}
