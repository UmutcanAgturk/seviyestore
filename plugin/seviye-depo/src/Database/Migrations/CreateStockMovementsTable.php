<?php

declare(strict_types=1);

namespace Seviye\Depo\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_stock_movements - append-only stok defteri (bkz.
 * Domain\StockMovement'in docblock'u). reference_type/reference_id
 * bilinçli olarak polimorfik (FK yok): bugün yalnızca "purchase_order"
 * referans veriyor, faz 2'de "stock_count" da referans verecek - tek bir
 * tabloya sabit bir FK, ikinci referans türü eklendiğinde kırılırdı.
 * product_id'de de FK yok, aynı "WP/WC çekirdek tablosuna FK yok" kuralı.
 */
final class CreateStockMovementsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_01_000005';
    }

    public function description(): string
    {
        return 'Creates the scp_stock_movements table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('stock_movements');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(30) NOT NULL,
            quantity_delta INT NOT NULL,
            reference_type VARCHAR(30) NULL,
            reference_id BIGINT UNSIGNED NULL,
            note VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY reference (reference_type, reference_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('stock_movements'));
    }
}
