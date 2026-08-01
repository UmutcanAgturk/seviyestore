<?php

declare(strict_types=1);

namespace Seviye\Depo\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_stock_count_items. stock_count_id ON DELETE CASCADE - bir
 * sayımın kalemleri o sayımdan bağımsız anlamlı değil, scp_purchase_order_items'ın
 * aynı ilkesi. product_id'de FK YOK (wp_posts, ürün/varyasyon).
 */
final class CreateStockCountItemsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_01_000007';
    }

    public function description(): string
    {
        return 'Creates the scp_stock_count_items table with a FK to scp_stock_counts.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('stock_count_items');
        $stockCountsTable = $connection->table('stock_counts');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            stock_count_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            expected_quantity INT NOT NULL,
            counted_quantity INT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY stock_count_id (stock_count_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_stock_count_id_fk',
            "FOREIGN KEY (stock_count_id) REFERENCES {$stockCountsTable} (id) ON DELETE CASCADE"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('stock_count_items'));
    }
}
