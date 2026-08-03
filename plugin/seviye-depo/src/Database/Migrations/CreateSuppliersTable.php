<?php

declare(strict_types=1);

namespace Seviye\Depo\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_suppliers - the tedarikçi kaydı, kendi tablosunda tutulur
 * (WordPress/WooCommerce çekirdek tablolarına dokunmaz). Basit bir CRUD
 * kaydı, mevcut scp_branches'ın tasarım düzeyiyle aynı.
 *
 * user_id (nullable) - "Tedarikçi portalı": bu tedarikçinin kendi satın
 * alma siparişlerini görüp "gönderildi" işaretleyebileceği bir WP hesabı,
 * varsa. Bilinçli olarak Core'un Role enum'ına yeni bir rol EKLEMİYOR (o
 * dokuz rol ürün spesifikasyonunun kapalı kümesi - bkz. Role'ün kendi
 * docblock'u); bunun yerine bu tablodaki basit bir bağlantı, erişim ise
 * RBAC/Capability sisteminin tamamen dışında, doğrudan "bu kullanıcı bir
 * tedarikçiye mi bağlı" kontrolüyle veriliyor - bkz.
 * Http\PurchaseOrdersRestController'ın supplier-scoped uç noktaları ve
 * docs/ARCHITECTURE.md.
 *
 * user_id, scp_users tablosuna değil WordPress'in kendi wp_users'ına işaret
 * ediyor - platformun genel "scp_* tablo wp_* tabloya FK içermez" kuralı
 * burada da geçerli, bu yüzden gerçek bir FOREIGN KEY yok, yalnızca bir
 * index.
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
            user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('suppliers'));
    }
}
