<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\SubeSiparis\Domain\BranchOrder;
use Seviye\SubeSiparis\Domain\BranchOrderItem;
use Seviye\SubeSiparis\Domain\BranchOrderStatus;

final class WpdbBranchOrderRepository implements BranchOrderRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(int $branchId, int $createdByUserId, ?string $note, array $items): BranchOrder
    {
        $now = $this->now();

        $this->connection->insert($this->connection->table('branch_orders'), [
            'branch_id' => $branchId,
            'status' => BranchOrderStatus::DRAFT->value,
            'created_by' => $createdByUserId,
            'note' => $note,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $orderId = $this->connection->lastInsertId();

        $this->insertItems($orderId, $items);

        $order = $this->find($orderId);

        if ($order === null) {
            throw new RuntimeException('Şube siparişi eklendikten sonra okunamadı.');
        }

        return $order;
    }

    public function find(int $id): ?BranchOrder
    {
        $header = $this->findHeaderRow($id);

        if ($header === null) {
            return null;
        }

        return $this->hydrateHeader($header, $this->itemsForOrders([$id])[$id] ?? []);
    }

    public function all(?BranchOrderStatus $status = null, int|false|null $branchId = false): array
    {
        $table = $this->connection->table('branch_orders');
        $conditions = [];
        $args = [];

        if ($status !== null) {
            $conditions[] = 'status = %s';
            $args[] = $status->value;
        }

        if ($branchId !== false) {
            if ($branchId === null) {
                $conditions[] = 'branch_id IS NULL';
            } else {
                $conditions[] = 'branch_id = %d';
                $args[] = $branchId;
            }
        }

        $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC";
        $sql = $args !== [] ? $this->connection->prepare($sql, $args) : $sql;

        $headers = $this->connection->getResults($sql);
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $headers);
        $itemsByOrder = $this->itemsForOrders($ids);

        return array_map(
            fn (array $row): BranchOrder => $this->hydrateHeader($row, $itemsByOrder[(int) $row['id']] ?? []),
            $headers
        );
    }

    public function replaceItems(int $id, array $items): BranchOrder
    {
        $table = $this->connection->table('branch_order_items');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE branch_order_id = %d", [$id]);
        $this->connection->query($sql);

        $this->insertItems($id, $items);

        $this->touch($id);

        $order = $this->find($id);

        if ($order === null) {
            throw new RuntimeException('Şube siparişi kalemleri güncellendikten sonra okunamadı.');
        }

        return $order;
    }

    public function submit(int $id): void
    {
        $table = $this->connection->table('branch_orders');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, submitted_at = %s, updated_at = %s WHERE id = %d",
            [BranchOrderStatus::SUBMITTED->value, $this->now(), $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    public function cancel(int $id): void
    {
        $this->setStatus($id, BranchOrderStatus::CANCELLED);
    }

    public function reject(int $id, string $reason): void
    {
        $table = $this->connection->table('branch_orders');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, rejected_reason = %s, updated_at = %s WHERE id = %d",
            [BranchOrderStatus::REJECTED->value, $reason, $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    public function approve(int $id, int $approvedByUserId, array $splitsByItemId): BranchOrder
    {
        $itemsTable = $this->connection->table('branch_order_items');
        $hasPaidPortion = false;

        foreach ($splitsByItemId as $itemId => $split) {
            $sql = $this->connection->prepare(
                "UPDATE {$itemsTable}
                 SET free_quantity_applied = %d, paid_quantity = %d, unit_price = %s, updated_at = %s
                 WHERE id = %d",
                [
                    $split['free'],
                    $split['paid'],
                    $split['unit_price'] !== null ? (string) $split['unit_price'] : null,
                    $this->now(),
                    $itemId,
                ]
            );

            $this->connection->query($sql);

            if ($split['paid'] > 0) {
                $hasPaidPortion = true;
            }
        }

        $newStatus = $hasPaidPortion ? BranchOrderStatus::AWAITING_PAYMENT : BranchOrderStatus::COMPLETED;

        $table = $this->connection->table('branch_orders');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, approved_by = %d, approved_at = %s, updated_at = %s WHERE id = %d",
            [$newStatus->value, $approvedByUserId, $this->now(), $this->now(), $id]
        );
        $this->connection->query($sql);

        $order = $this->find($id);

        if ($order === null) {
            throw new RuntimeException('Şube siparişi onaylandıktan sonra okunamadı.');
        }

        return $order;
    }

    public function attachWcOrder(int $id, int $wcOrderId): void
    {
        $table = $this->connection->table('branch_orders');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET wc_order_id = %d, updated_at = %s WHERE id = %d",
            [$wcOrderId, $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    public function markCompleted(int $id): void
    {
        $this->setStatus($id, BranchOrderStatus::COMPLETED);
    }

    public function findItem(int $itemId): ?BranchOrderItem
    {
        $table = $this->connection->table('branch_order_items');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$itemId]);
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrateItem($rows[0]) : null;
    }

    public function consumedFreeQuantity(int $branchId, int $productId): int
    {
        $itemsTable = $this->connection->table('branch_order_items');
        $ordersTable = $this->connection->table('branch_orders');

        $lockedStatuses = [BranchOrderStatus::AWAITING_PAYMENT->value, BranchOrderStatus::COMPLETED->value];
        $placeholders = implode(',', array_fill(0, count($lockedStatuses), '%s'));

        $sql = $this->connection->prepare(
            "SELECT COALESCE(SUM(i.free_quantity_applied), 0) AS total
             FROM {$itemsTable} i
             INNER JOIN {$ordersTable} o ON o.id = i.branch_order_id
             WHERE o.branch_id = %d AND i.product_id = %d AND o.status IN ({$placeholders})",
            [$branchId, $productId, ...$lockedStatuses]
        );

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]['total']) ? (int) $rows[0]['total'] : 0;
    }

    /**
     * @param list<array{product_id: int, quantity_requested: int}> $items
     */
    private function insertItems(int $orderId, array $items): void
    {
        $now = $this->now();

        foreach ($items as $item) {
            $this->connection->insert($this->connection->table('branch_order_items'), [
                'branch_order_id' => $orderId,
                'product_id' => $item['product_id'],
                'quantity_requested' => $item['quantity_requested'],
                'free_quantity_applied' => 0,
                'paid_quantity' => 0,
                'unit_price' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function setStatus(int $id, BranchOrderStatus $status): void
    {
        $table = $this->connection->table('branch_orders');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
            [$status->value, $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    private function touch(int $id): void
    {
        $table = $this->connection->table('branch_orders');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET updated_at = %s WHERE id = %d",
            [$this->now(), $id]
        );

        $this->connection->query($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findHeaderRow(int $id): ?array
    {
        $table = $this->connection->table('branch_orders');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        return $rows[0] ?? null;
    }

    /**
     * @param list<int> $orderIds
     * @return array<int, list<BranchOrderItem>>
     */
    private function itemsForOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $table = $this->connection->table('branch_order_items');
        $placeholders = implode(',', array_fill(0, count($orderIds), '%d'));
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE branch_order_id IN ({$placeholders}) ORDER BY id ASC",
            $orderIds
        );

        $grouped = [];

        foreach ($this->connection->getResults($sql) as $row) {
            $item = $this->hydrateItem($row);
            $grouped[$item->branchOrderId][] = $item;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<BranchOrderItem> $items
     */
    private function hydrateHeader(array $row, array $items): BranchOrder
    {
        return new BranchOrder(
            (int) $row['id'],
            (int) $row['branch_id'],
            BranchOrderStatus::from((string) $row['status']),
            (int) $row['created_by'],
            isset($row['note']) && $row['note'] !== '' && $row['note'] !== null ? (string) $row['note'] : null,
            (string) $row['created_at'],
            $items,
            isset($row['submitted_at']) && $row['submitted_at'] !== null && $row['submitted_at'] !== ''
                ? (string) $row['submitted_at']
                : null,
            isset($row['approved_by']) && $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
            isset($row['approved_at']) && $row['approved_at'] !== null && $row['approved_at'] !== ''
                ? (string) $row['approved_at']
                : null,
            isset($row['rejected_reason']) && $row['rejected_reason'] !== null && $row['rejected_reason'] !== ''
                ? (string) $row['rejected_reason']
                : null,
            isset($row['wc_order_id']) && $row['wc_order_id'] !== null ? (int) $row['wc_order_id'] : null
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateItem(array $row): BranchOrderItem
    {
        return new BranchOrderItem(
            (int) $row['id'],
            (int) $row['branch_order_id'],
            (int) $row['product_id'],
            (int) $row['quantity_requested'],
            (int) $row['free_quantity_applied'],
            (int) $row['paid_quantity'],
            isset($row['unit_price']) && $row['unit_price'] !== null ? (float) $row['unit_price'] : null
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
