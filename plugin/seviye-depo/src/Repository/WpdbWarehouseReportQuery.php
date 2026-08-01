<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Depo\Contracts\PurchaseOrderReportFilter;
use Seviye\Depo\Contracts\PurchaseOrderReportRecord;
use Seviye\Depo\Contracts\WarehouseReportQueryInterface;
use Seviye\Depo\Domain\PurchaseOrderStatus;

/**
 * A separate, minimal adapter rather than reusing
 * {@see WpdbPurchaseOrderRepository}: that class's methods return the full
 * Domain\PurchaseOrder (item-level, no pre-aggregated cost), which has no
 * business being depended on by another module. Mirrors
 * Seviye\Commerce\Repository\WpdbOrderLineItemQuery.
 *
 * `total_cost` is computed with a single LEFT JOIN against a
 * GROUP BY subquery over scp_purchase_order_items rather than N+1 queries
 * per order - the same "one query, not one per row" principle
 * WpdbPurchaseOrderRepository::itemsForOrders() already applies.
 */
final class WpdbWarehouseReportQuery implements WarehouseReportQueryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function search(PurchaseOrderReportFilter $filter): array
    {
        $ordersTable = $this->connection->table('purchase_orders');
        $itemsTable = $this->connection->table('purchase_order_items');
        $conditions = [];
        $args = [];

        if ($filter->supplierId !== null) {
            $conditions[] = 'po.supplier_id = %d';
            $args[] = $filter->supplierId;
        }

        if ($filter->fromDate !== null) {
            $conditions[] = 'po.created_at >= %s';
            $args[] = $filter->fromDate . ' 00:00:00';
        }

        if ($filter->toDate !== null) {
            $conditions[] = 'po.created_at <= %s';
            $args[] = $filter->toDate . ' 23:59:59';
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $sql = "SELECT po.*, COALESCE(item_totals.total_cost, 0) AS total_cost
            FROM {$ordersTable} po
            LEFT JOIN (
                SELECT purchase_order_id, SUM(quantity_ordered * IFNULL(unit_cost, 0)) AS total_cost
                FROM {$itemsTable}
                GROUP BY purchase_order_id
            ) item_totals ON item_totals.purchase_order_id = po.id
            {$where}
            ORDER BY po.created_at DESC, po.id DESC";

        if ($args !== []) {
            $sql = $this->connection->prepare($sql, $args);
        }

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PurchaseOrderReportRecord
    {
        $status = (string) $row['status'];

        return new PurchaseOrderReportRecord(
            (int) $row['id'],
            (int) $row['supplier_id'],
            $status,
            isset($row['expected_date']) && $row['expected_date'] !== '' && $row['expected_date'] !== null
                ? (string) $row['expected_date']
                : null,
            $status === PurchaseOrderStatus::COMPLETED->value ? (string) $row['updated_at'] : null,
            (float) $row['total_cost'],
            (string) $row['created_at']
        );
    }
}
