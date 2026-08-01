<?php

declare(strict_types=1);

namespace Seviye\Depo\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_suppliers - the tedarikçi kaydı, kendi tablosunda tutulur
 * (WordPress/WooCommerce çekirdek tablolarına dokunmaz). Basit bir CRUD
 * kaydı, mevcut scp_branches'ın tasarım düzeyiyle aynı.
 */
final class CreateSuppliersTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_01_000002';
    }

    public function description(): string
    {
        return 'Creates the scp_suppliers table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('suppliers');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            contact_name VARCHAR(190) NULL,
            phone VARCHAR(20) NULL,
            email VARCHAR(190) NULL,
            tax_number VARCHAR(20) NULL,
            address TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('suppliers'));
    }
}
