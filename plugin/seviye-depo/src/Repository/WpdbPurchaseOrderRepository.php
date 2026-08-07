<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Depo\Domain\PurchaseOrder;
use Seviye\Depo\Domain\PurchaseOrderItem;
use Seviye\Depo\Domain\PurchaseOrderStatus;
use Seviye\Depo\Support\PurchaseOrderStatusCalculator;

final class WpdbPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PurchaseOrderStatusCalculator $statusCalculator
    ) {
    }

    public function create(
        int $supplierId,
        ?string $expectedDate,
        ?string $note,
        int $createdByUserId,
        array $items,
        ?int $branchId = null
    ): PurchaseOrder {
        $now = $this->now();

        $this->connection->insert($this->connection->table('purchase_orders'), [
            'supplier_id' => $supplierId,
            'code' => $this->nextCode(),
            'status' => PurchaseOrderStatus::DRAFT->value,
            'expected_date' => $expectedDate,
            'note' => $note,
            'created_by' => $createdByUserId,
            'branch_id' => $branchId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $purchaseOrderId = $this->connection->lastInsertId();

        foreach ($items as $item) {
            $this->connection->insert($this->connection->table('purchase_order_items'), [
                'purchase_order_id' => $purchaseOrderId,
                'product_id' => $item['product_id'],
                'quantity_ordered' => $item['quantity_ordered'],
                'quantity_received' => 0,
                'unit_cost' => $item['unit_cost'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $purchaseOrder = $this->find($purchaseOrderId);

        if ($purchaseOrder === null) {
            throw new RuntimeException('Satın alma siparişi eklendikten sonra okunamadı.');
        }

        return $purchaseOrder;
    }

    public function find(int $id): ?PurchaseOrder
    {
        $header = $this->findHeaderRow($id);

        if ($header === null) {
            return null;
        }

        return $this->hydrateHeader($header, $this->itemsForOrders([$id])[$id] ?? []);
    }

    public function all(?PurchaseOrderStatus $status = null, ?int $supplierId = null, int|false|null $branchId = false): array
    {
        $table = $this->connection->table('purchase_orders');
        $conditions = [];
        $args = [];

        if ($status !== null) {
            $conditions[] = 'status = %s';
            $args[] = $status->value;
        }

        if ($supplierId !== null) {
            $conditions[] = 'supplier_id = %d';
            $args[] = $supplierId;
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
            fn (array $row): PurchaseOrder => $this->hydrateHeader($row, $itemsByOrder[(int) $row['id']] ?? []),
            $headers
        );
    }

    public function send(int $id): void
    {
        $this->setStatus($id, PurchaseOrderStatus::SENT);
    }

    public function cancel(int $id): void
    {
        $this->setStatus($id, PurchaseOrderStatus::CANCELLED);
    }

    public function markShipped(int $id): void
    {
        $table = $this->connection->table('purchase_orders');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET supplier_shipped_at = %s, updated_at = %s WHERE id = %d",
            [$this->now(), $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    public function receiveItem(int $itemId, int $quantity): PurchaseOrderItem
    {
        $table = $this->connection->table('purchase_order_items');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET quantity_received = quantity_received + %d, updated_at = %s WHERE id = %d",
            [$quantity, $this->now(), $itemId]
        );
        $this->connection->query($sql);

        $item = $this->findItem($itemId);

        if ($item === null) {
            throw new RuntimeException('Satın alma kalemi bulunamadı.');
        }

        $this->recalculateStatus($item->purchaseOrderId);

        return $item;
    }

    public function findItem(int $itemId): ?PurchaseOrderItem
    {
        $table = $this->connection->table('purchase_order_items');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$itemId]);
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrateItem($rows[0]) : null;
    }

    /**
     * Sent/partially_received bir sipariş, kalemlerinin toplam durumuna
     * göre otomatik olarak partially_received/completed'e geçer - draft/
     * cancelled/completed'e dokunulmaz. Karar mantığı
     * {@see PurchaseOrderStatusCalculator}'da, burada yalnızca okuma/yazma var.
     */
    private function recalculateStatus(int $purchaseOrderId): void
    {
        $header = $this->findHeaderRow($purchaseOrderId);

        if ($header === null) {
            return;
        }

        $status = PurchaseOrderStatus::from((string) $header['status']);
        $items = $this->itemsForOrders([$purchaseOrderId])[$purchaseOrderId] ?? [];
        $newStatus = $this->statusCalculator->recalculate($status, $items);

        if ($newStatus !== $status) {
            $this->setStatus($purchaseOrderId, $newStatus);
        }
    }

    private function setStatus(int $id, PurchaseOrderStatus $status): void
    {
        $table = $this->connection->table('purchase_orders');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
            [$status->value, $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findHeaderRow(int $id): ?array
    {
        $table = $this->connection->table('purchase_orders');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        return $rows[0] ?? null;
    }

    /**
     * @param list<int> $purchaseOrderIds
     * @return array<int, list<PurchaseOrderItem>>
     */
    private function itemsForOrders(array $purchaseOrderIds): array
    {
        if ($purchaseOrderIds === []) {
            return [];
        }

        $table = $this->connection->table('purchase_order_items');
        $placeholders = implode(',', array_fill(0, count($purchaseOrderIds), '%d'));
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE purchase_order_id IN ({$placeholders}) ORDER BY id ASC",
            $purchaseOrderIds
        );

        $grouped = [];

        foreach ($this->connection->getResults($sql) as $row) {
            $item = $this->hydrateItem($row);
            $grouped[$item->purchaseOrderId][] = $item;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<PurchaseOrderItem> $items
     */
    private function hydrateHeader(array $row, array $items): PurchaseOrder
    {
        $shippedAt = $row['supplier_shipped_at'] ?? null;

        return new PurchaseOrder(
            (int) $row['id'],
            (int) $row['supplier_id'],
            (string) $row['code'],
            PurchaseOrderStatus::from((string) $row['status']),
            isset($row['expected_date']) && $row['expected_date'] !== '' && $row['expected_date'] !== null
                ? (string) $row['expected_date']
                : null,
            isset($row['note']) && $row['note'] !== '' ? (string) $row['note'] : null,
            (int) $row['created_by'],
            (string) $row['created_at'],
            $items,
            $shippedAt !== null && $shippedAt !== '' ? (string) $shippedAt : null,
            isset($row['branch_id']) && $row['branch_id'] !== null ? (int) $row['branch_id'] : null
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateItem(array $row): PurchaseOrderItem
    {
        return new PurchaseOrderItem(
            (int) $row['id'],
            (int) $row['purchase_order_id'],
            (int) $row['product_id'],
            (int) $row['quantity_ordered'],
            (int) $row['quantity_received'],
            isset($row['unit_cost']) && $row['unit_cost'] !== null ? (float) $row['unit_cost'] : null
        );
    }

    private function nextCode(): string
    {
        $year = gmdate('Y');
        $table = $this->connection->table('purchase_orders');
        $sql = $this->connection->prepare(
            "SELECT COUNT(*) AS total FROM {$table} WHERE code LIKE %s",
            ["PO-{$year}-%"]
        );
        $rows = $this->connection->getResults($sql);
        $count = isset($rows[0]['total']) ? (int) $rows[0]['total'] : 0;

        return sprintf('PO-%s-%04d', $year, $count + 1);
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
