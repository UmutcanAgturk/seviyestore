<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Depo\Domain\StockMovement;
use Seviye\Depo\Domain\StockMovementType;

final class WpdbStockMovementRepository implements StockMovementRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function record(
        int $productId,
        StockMovementType $type,
        int $quantityDelta,
        ?string $referenceType,
        ?int $referenceId,
        ?string $note,
        int $createdByUserId,
        ?int $branchId = null
    ): StockMovement {
        $this->connection->insert($this->connection->table('stock_movements'), [
            'product_id' => $productId,
            'type' => $type->value,
            'quantity_delta' => $quantityDelta,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_by' => $createdByUserId,
            'branch_id' => $branchId,
            'created_at' => $this->now(),
        ]);

        $table = $this->connection->table('stock_movements');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            [$this->connection->lastInsertId()]
        );
        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            throw new RuntimeException('Stok hareketi kaydedildikten sonra okunamadı.');
        }

        return $this->hydrate($rows[0]);
    }

    public function list(
        ?int $productId = null,
        ?StockMovementType $type = null,
        ?string $from = null,
        ?string $to = null,
        int|false|null $branchId = false
    ): array {
        $table = $this->connection->table('stock_movements');
        $conditions = [];
        $args = [];

        if ($productId !== null) {
            $conditions[] = 'product_id = %d';
            $args[] = $productId;
        }

        if ($type !== null) {
            $conditions[] = 'type = %s';
            $args[] = $type->value;
        }

        if ($from !== null) {
            $conditions[] = 'created_at >= %s';
            $args[] = $from . ' 00:00:00';
        }

        if ($to !== null) {
            $conditions[] = 'created_at <= %s';
            $args[] = $to . ' 23:59:59';
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

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): StockMovement
    {
        return new StockMovement(
            (int) $row['id'],
            (int) $row['product_id'],
            StockMovementType::from((string) $row['type']),
            (int) $row['quantity_delta'],
            isset($row['reference_type']) && $row['reference_type'] !== '' ? (string) $row['reference_type'] : null,
            isset($row['reference_id']) && $row['reference_id'] !== null ? (int) $row['reference_id'] : null,
            isset($row['note']) && $row['note'] !== '' ? (string) $row['note'] : null,
            (int) $row['created_by'],
            (string) $row['created_at'],
            isset($row['branch_id']) && $row['branch_id'] !== null ? (int) $row['branch_id'] : null
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
