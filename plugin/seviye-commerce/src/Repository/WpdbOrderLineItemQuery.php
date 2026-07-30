<?php

declare(strict_types=1);

namespace Seviye\Commerce\Repository;

use Seviye\Commerce\Contracts\OrderLineItemFilter;
use Seviye\Commerce\Contracts\OrderLineItemQueryInterface;
use Seviye\Commerce\Contracts\OrderLineItemRecord;
use Seviye\Core\Database\ConnectionInterface;

/**
 * A separate, minimal adapter rather than reusing
 * {@see WpdbOrderLineItemRepository}: that class's methods return the full
 * Domain\OrderLineItem (no createdAt, no filtering), which has no business
 * being depended on by another module. Mirrors
 * Seviye\Students\Repository\WpdbStudentLookup.
 */
final class WpdbOrderLineItemQuery implements OrderLineItemQueryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function search(OrderLineItemFilter $filter): array
    {
        $table = $this->connection->table('order_line_items');
        $conditions = [];
        $args = [];

        if ($filter->branchId !== null) {
            $conditions[] = 'branch_id = %d';
            $args[] = $filter->branchId;
        }

        if ($filter->productId !== null) {
            $conditions[] = 'product_id = %d';
            $args[] = $filter->productId;
        }

        if ($filter->fromDate !== null) {
            $conditions[] = 'created_at >= %s';
            $args[] = $filter->fromDate . ' 00:00:00';
        }

        if ($filter->toDate !== null) {
            $conditions[] = 'created_at <= %s';
            $args[] = $filter->toDate . ' 23:59:59';
        }

        if ($filter->status !== null) {
            $conditions[] = 'status = %s';
            $args[] = $filter->status;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $sql = "SELECT * FROM {$table}{$where} ORDER BY created_at ASC";

        if ($args !== []) {
            $sql = $this->connection->prepare($sql, $args);
        }

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): OrderLineItemRecord
    {
        return new OrderLineItemRecord(
            (int) $row['id'],
            (int) $row['order_id'],
            (int) $row['order_item_id'],
            (int) $row['student_id'],
            (int) $row['branch_id'],
            (int) $row['product_id'],
            (float) $row['commission_rate'],
            (float) $row['price'],
            (float) $row['vat_amount'],
            (string) $row['status'],
            (string) $row['created_at']
        );
    }
}
