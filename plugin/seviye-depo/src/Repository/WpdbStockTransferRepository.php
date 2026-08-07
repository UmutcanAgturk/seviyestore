<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Depo\Domain\StockTransfer;
use Seviye\Depo\Domain\StockTransferStatus;

final class WpdbStockTransferRepository implements StockTransferRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(
        int $fromProductId,
        int $toProductId,
        int $quantity,
        ?int $fromBranchId,
        ?int $toBranchId,
        ?string $note,
        int $requestedByUserId
    ): StockTransfer {
        $now = $this->now();

        $this->connection->insert($this->connection->table('stock_transfers'), [
            'from_product_id' => $fromProductId,
            'to_product_id' => $toProductId,
            'quantity' => $quantity,
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
            'status' => StockTransferStatus::PENDING->value,
            'note' => $note,
            'requested_by' => $requestedByUserId,
            'completed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $transfer = $this->find($this->connection->lastInsertId());

        if ($transfer === null) {
            throw new RuntimeException('Stok transferi eklendikten sonra okunamadı.');
        }

        return $transfer;
    }

    public function find(int $id): ?StockTransfer
    {
        $table = $this->connection->table('stock_transfers');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function all(?StockTransferStatus $status = null, int|false|null $branchId = false): array
    {
        $table = $this->connection->table('stock_transfers');
        $conditions = [];
        $args = [];

        if ($status !== null) {
            $conditions[] = 'status = %s';
            $args[] = $status->value;
        }

        if ($branchId !== false) {
            if ($branchId === null) {
                $conditions[] = '(from_branch_id IS NULL OR to_branch_id IS NULL)';
            } else {
                $conditions[] = '(from_branch_id = %d OR to_branch_id = %d)';
                $args[] = $branchId;
                $args[] = $branchId;
            }
        }

        $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC";
        $sql = $args !== [] ? $this->connection->prepare($sql, $args) : $sql;

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    public function complete(int $id, int $completedByUserId): void
    {
        $table = $this->connection->table('stock_transfers');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, completed_by = %d, updated_at = %s WHERE id = %d",
            [StockTransferStatus::COMPLETED->value, $completedByUserId, $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    public function cancel(int $id): void
    {
        $table = $this->connection->table('stock_transfers');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
            [StockTransferStatus::CANCELLED->value, $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): StockTransfer
    {
        $status = StockTransferStatus::from((string) $row['status']);
        $completedAt = $row['updated_at'] ?? null;

        return new StockTransfer(
            (int) $row['id'],
            (int) $row['from_product_id'],
            (int) $row['to_product_id'],
            (int) $row['quantity'],
            isset($row['from_branch_id']) && $row['from_branch_id'] !== null ? (int) $row['from_branch_id'] : null,
            isset($row['to_branch_id']) && $row['to_branch_id'] !== null ? (int) $row['to_branch_id'] : null,
            $status,
            isset($row['note']) && $row['note'] !== '' ? (string) $row['note'] : null,
            (int) $row['requested_by'],
            isset($row['completed_by']) && $row['completed_by'] !== null ? (int) $row['completed_by'] : null,
            (string) $row['created_at'],
            $status !== StockTransferStatus::PENDING && $completedAt !== null ? (string) $completedAt : null
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
