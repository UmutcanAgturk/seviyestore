<?php

declare(strict_types=1);

namespace Seviye\Commerce\Repository;

use RuntimeException;
use Seviye\Commerce\Domain\OrderLineItem;
use Seviye\Core\Database\ConnectionInterface;

final class WpdbOrderLineItemRepository implements OrderLineItemRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(
        int $orderId,
        int $orderItemId,
        int $studentId,
        int $branchId,
        int $productId,
        float $commissionRate,
        float $price,
        float $vatAmount,
        string $status
    ): OrderLineItem {
        $now = $this->now();

        $this->connection->insert($this->connection->table('order_line_items'), [
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'student_id' => $studentId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'commission_rate' => $commissionRate,
            'price' => $price,
            'vat_amount' => $vatAmount,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $table = $this->connection->table('order_line_items');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            [$this->connection->lastInsertId()]
        );
        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            throw new RuntimeException('Order line item could not be read back after insert.');
        }

        return $this->hydrate($rows[0]);
    }

    public function updateStatusForOrder(int $orderId, string $status): void
    {
        $table = $this->connection->table('order_line_items');
        $sql = $this->connection->prepare(
            'UPDATE ' . $table . ' SET status = %s, updated_at = %s WHERE order_id = %d',
            [$status, $this->now(), $orderId]
        );

        $this->connection->query($sql);
    }

    public function findByOrder(int $orderId): array
    {
        $table = $this->connection->table('order_line_items');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC",
            [$orderId]
        );

        $rows = $this->connection->getResults($sql);

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): OrderLineItem
    {
        return new OrderLineItem(
            (int) $row['id'],
            (int) $row['order_id'],
            (int) $row['order_item_id'],
            (int) $row['student_id'],
            (int) $row['branch_id'],
            (int) $row['product_id'],
            (float) $row['commission_rate'],
            (float) $row['price'],
            (float) $row['vat_amount'],
            (string) $row['status']
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
