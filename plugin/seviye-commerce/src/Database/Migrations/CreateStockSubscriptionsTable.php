<?php

declare(strict_types=1);

namespace Seviye\Commerce\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_stock_subscriptions - "stok gelince haber ver": a veli, on a
 * product page for an out-of-stock product, subscribes to be notified when
 * it's back in stock. product_id/user_id have no FK (both point at
 * WooCommerce's/WordPress' own core tables - wp_posts/wp_users - and this
 * platform never puts FKs on WordPress/WooCommerce core tables, see
 * docs/database/README.md and e.g. scp_order_line_items.product_id).
 *
 * UNIQUE KEY on (product_id, user_id): re-subscribing to the same product
 * is a no-op (see {@see \Seviye\Commerce\Repository\WpdbStockSubscriptionRepository::subscribe()}'s
 * ON DUPLICATE KEY UPDATE), never a duplicate row.
 *
 * A row is deleted once its notification fires (see
 * Http\BackInStockNotificationHooks) - this is a ONE-TIME "notify me next
 * time" subscription, not a persistent watch; a veli who wants to be
 * notified again after the product goes out of stock a second time
 * re-subscribes.
 */
final class CreateStockSubscriptionsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_06_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_stock_subscriptions table.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('stock_subscriptions');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY product_user (product_id, user_id),
            KEY user_id (user_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('stock_subscriptions'));
    }
}
