<?php

declare(strict_types=1);

namespace Seviye\Depo\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_stock_transfers - "Şubeler arası stok transferi" (bkz.
 * Domain\StockTransfer'ın docblock'u). `from_product_id`/`to_product_id`'de
 * FK yok, diğer tüm `product_id` alanları gibi WC çekirdek tablosuna hiçbir
 * yerde FK konmuyor. `from_branch_id`/`to_branch_id` her ikisi de nullable
 * (NULL = Genel Merkez deposu) - StockTransfersRestController::store()
 * bunları isteğin gövdesinden DEĞİL, iki ürünün kendi sahiplik meta'sından
 * (scp_commerce_product_owner_branch_id filtre köprüsü) yazma anında
 * çözüp donduruyor.
 *
 * Ayrı bir `completed_at` sütunu YOK - `scp_purchase_orders`'ın "durum
 * geçişine özel zaman damgası yok" ilkesiyle aynı: `created_at` açılışı,
 * `status=completed` (ya da cancelled) olduğunda `updated_at` kapanışı
 * temsil ediyor.
 */
final class CreateStockTransfersTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_01_000009';
    }

    public function description(): string
    {
        return 'Creates the scp_stock_transfers table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('stock_transfers');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            from_product_id BIGINT UNSIGNED NOT NULL,
            to_product_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            from_branch_id BIGINT UNSIGNED NULL,
            to_branch_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            note VARCHAR(255) NULL,
            requested_by BIGINT UNSIGNED NOT NULL,
            completed_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY from_product_id (from_product_id),
            KEY to_product_id (to_product_id),
            KEY from_branch_id (from_branch_id),
            KEY to_branch_id (to_branch_id),
            KEY status (status)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('stock_transfers'));
    }
}
