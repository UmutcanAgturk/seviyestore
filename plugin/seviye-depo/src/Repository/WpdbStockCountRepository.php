<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Depo\Domain\StockCount;
use Seviye\Depo\Domain\StockCountItem;
use Seviye\Depo\Domain\StockCountStatus;

final class WpdbStockCountRepository implements StockCountRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function open(array $productStockLevels, int $startedByUserId, ?int $branchId = null): StockCount
    {
        $now = $this->now();

        $this->connection->insert($this->connection->table('stock_counts'), [
            'status' => StockCountStatus::OPEN->value,
            'started_by' => $startedByUserId,
            'completed_by' => null,
            'branch_id' => $branchId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stockCountId = $this->connection->lastInsertId();

        foreach ($productStockLevels as $productId => $quantity) {
            $this->connection->insert($this->connection->table('stock_count_items'), [
                'stock_count_id' => $stockCountId,
                'product_id' => $productId,
                'expected_quantity' => $quantity,
                'counted_quantity' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $stockCount = $this->find($stockCountId);

        if ($stockCount === null) {
            throw new RuntimeException('Stok sayımı eklendikten sonra okunamadı.');
        }

        return $stockCount;
    }

    public function find(int $id): ?StockCount
    {
        $header = $this->findHeaderRow($id);

        if ($header === null) {
            return null;
        }

        return $this->hydrateHeader($header, $this->itemsForCounts([$id])[$id] ?? []);
    }

    public function all(?StockCountStatus $status = null, int|false|null $branchId = false): array
    {
        $table = $this->connection->table('stock_counts');
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
        $itemsByCount = $this->itemsForCounts($ids);

        return array_map(
            fn (array $row): StockCount => $this->hydrateHeader($row, $itemsByCount[(int) $row['id']] ?? []),
            $headers
        );
    }

    public function setCountedQuantity(int $itemId, int $countedQuantity): StockCountItem
    {
        $table = $this->connection->table('stock_count_items');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET counted_quantity = %d, updated_at = %s WHERE id = %d",
            [$countedQuantity, $this->now(), $itemId]
        );
        $this->connection->query($sql);

        $item = $this->findItem($itemId);

        if ($item === null) {
            throw new RuntimeException('Sayım kalemi bulunamadı.');
        }

        return $item;
    }

    public function findItem(int $itemId): ?StockCountItem
    {
        $table = $this->connection->table('stock_count_items');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$itemId]);
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrateItem($rows[0]) : null;
    }

    public function complete(int $id, int $completedByUserId): StockCount
    {
        $table = $this->connection->table('stock_counts');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, completed_by = %d, updated_at = %s WHERE id = %d",
            [StockCountStatus::COMPLETED->value, $completedByUserId, $this->now(), $id]
        );
        $this->connection->query($sql);

        $stockCount = $this->find($id);

        if ($stockCount === null) {
            throw new RuntimeException('Stok sayımı tamamlandıktan sonra okunamadı.');
        }

        return $stockCount;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findHeaderRow(int $id): ?array
    {
        $table = $this->connection->table('stock_counts');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        return $rows[0] ?? null;
    }

    /**
     * @param list<int> $stockCountIds
     * @return array<int, list<StockCountItem>>
     */
    private function itemsForCounts(array $stockCountIds): array
    {
        if ($stockCountIds === []) {
            return [];
        }

        $table = $this->connection->table('stock_count_items');
        $placeholders = implode(',', array_fill(0, count($stockCountIds), '%d'));
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE stock_count_id IN ({$placeholders}) ORDER BY id ASC",
            $stockCountIds
        );

        $grouped = [];

        foreach ($this->connection->getResults($sql) as $row) {
            $item = $this->hydrateItem($row);
            $grouped[$item->stockCountId][] = $item;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<StockCountItem> $items
     */
    private function hydrateHeader(array $row, array $items): StockCount
    {
        $status = StockCountStatus::from((string) $row['status']);

        return new StockCount(
            (int) $row['id'],
            $status,
            (int) $row['started_by'],
            isset($row['completed_by']) && $row['completed_by'] !== null ? (int) $row['completed_by'] : null,
            (string) $row['created_at'],
            $status === StockCountStatus::COMPLETED ? (string) $row['updated_at'] : null,
            $items,
            isset($row['branch_id']) && $row['branch_id'] !== null ? (int) $row['branch_id'] : null
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateItem(array $row): StockCountItem
    {
        return new StockCountItem(
            (int) $row['id'],
            (int) $row['stock_count_id'],
            (int) $row['product_id'],
            (int) $row['expected_quantity'],
            isset($row['counted_quantity']) && $row['counted_quantity'] !== null ? (int) $row['counted_quantity'] : null
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
