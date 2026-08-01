<?php

declare(strict_types=1);

namespace Seviye\Depo\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_stock_counts - bir sayım oturumunun başlığı (faz 2). Kalemleri
 * ayrı bir tabloda (scp_stock_count_items, bkz. CreateStockCountItemsTable).
 * Ayrı started_at/completed_at sütunu YOK - created_at açılış anını,
 * status=completed olduğunda updated_at kapanış anını temsil eder;
 * scp_purchase_orders'ın "durum geçişine özel zaman damgası yok" ilkesiyle
 * aynı.
 */
final class CreateStockCountsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_01_000006';
    }

    public function description(): string
    {
        return 'Creates the scp_stock_counts table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('stock_counts');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            started_by BIGINT UNSIGNED NOT NULL,
            completed_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('stock_counts'));
    }
}
