<?php

declare(strict_types=1);

namespace Seviye\Depo\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_purchase_suggestions - "düşük stok uyarısının otomatik satın
 * alma önerisine bağlanması" (faz 2, plan dokümanı). Her satır
 * LowStockPurchaseSuggestionListener tarafından commerce.product_low_stock
 * event'ine tepkiyle açılır. converted_purchase_order_id'de FK VAR (aksine
 * product_id'nin FK'sız olmasına) çünkü bu, WC'nin değil platformun kendi
 * scp_purchase_orders tablosuna işaret ediyor - purchase_order_items'la aynı
 * ilke. Satın alma siparişlerinin silme yolu olmadığından (yalnızca
 * cancel()) CASCADE/SET NULL'a gerek yok, varsayılan RESTRICT yeterli.
 */
final class CreatePurchaseSuggestionsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_01_000008';
    }

    public function description(): string
    {
        return 'Creates the scp_purchase_suggestions table with a FK to scp_purchase_orders.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('purchase_suggestions');
        $purchaseOrdersTable = $connection->table('purchase_orders');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            suggested_quantity INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            reason VARCHAR(255) NULL,
            converted_purchase_order_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY status (status)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_converted_purchase_order_id_fk',
            "FOREIGN KEY (converted_purchase_order_id) REFERENCES {$purchaseOrdersTable} (id)"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('purchase_suggestions'));
    }
}
