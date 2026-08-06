<?php

declare(strict_types=1);

namespace Seviye\Commerce\Repository;

use Seviye\Core\Database\ConnectionInterface;

final class WpdbStockSubscriptionRepository implements StockSubscriptionRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function subscribe(int $productId, int $userId): void
    {
        $table = $this->connection->table('stock_subscriptions');

        $sql = $this->connection->prepare(
            'INSERT INTO ' . $table . ' (product_id, user_id, created_at) '
                . 'VALUES (%d, %d, %s) '
                . 'ON DUPLICATE KEY UPDATE product_id = VALUES(product_id)',
            [$productId, $userId, $this->now()]
        );

        $this->connection->query($sql);
    }

    public function unsubscribe(int $productId, int $userId): void
    {
        $table = $this->connection->table('stock_subscriptions');
        $sql = $this->connection->prepare(
            "DELETE FROM {$table} WHERE product_id = %d AND user_id = %d",
            [$productId, $userId]
        );

        $this->connection->query($sql);
    }

    public function isSubscribed(int $productId, int $userId): bool
    {
        $table = $this->connection->table('stock_subscriptions');
        $sql = $this->connection->prepare(
            "SELECT id FROM {$table} WHERE product_id = %d AND user_id = %d LIMIT 1",
            [$productId, $userId]
        );

        return $this->connection->getResults($sql) !== [];
    }

    public function subscriberIdsFor(int $productId): array
    {
        $table = $this->connection->table('stock_subscriptions');
        $sql = $this->connection->prepare(
            "SELECT user_id FROM {$table} WHERE product_id = %d",
            [$productId]
        );

        return array_map(
            static fn (array $row): int => (int) $row['user_id'],
            $this->connection->getResults($sql)
        );
    }

    public function deleteAllFor(int $productId): void
    {
        $table = $this->connection->table('stock_subscriptions');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE product_id = %d", [$productId]);

        $this->connection->query($sql);
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
