<?php

declare(strict_types=1);

namespace Seviye\Commerce\Repository;

use Seviye\Commerce\Domain\ProductBranchStatus;
use Seviye\Core\Database\ConnectionInterface;

final class WpdbProductBranchVisibilityRepository implements ProductBranchVisibilityRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function setStatus(int $productId, int $branchId, ProductBranchStatus $status): void
    {
        $table = $this->connection->table('product_branches');
        $now = $this->now();

        $sql = $this->connection->prepare(
            'INSERT INTO ' . $table . ' (product_id, branch_id, status, created_at, updated_at) '
                . 'VALUES (%d, %d, %s, %s, %s) '
                . 'ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at)',
            [$productId, $branchId, $status->value, $now, $now]
        );

        $this->connection->query($sql);
    }

    public function isActiveForBranch(int $productId, int $branchId): bool
    {
        $table = $this->connection->table('product_branches');
        $sql = $this->connection->prepare(
            'SELECT status FROM ' . $table . ' WHERE product_id = %d AND branch_id = %d LIMIT 1',
            [$productId, $branchId]
        );

        $rows = $this->connection->getResults($sql);

        if ($rows === []) {
            // Opt-out model - see CreateProductBranchesTable's docblock.
            return true;
        }

        return ProductBranchStatus::from((string) $rows[0]['status']) === ProductBranchStatus::ACTIVE;
    }

    public function statusesForProduct(int $productId): array
    {
        $table = $this->connection->table('product_branches');
        $sql = $this->connection->prepare(
            "SELECT branch_id, status FROM {$table} WHERE product_id = %d",
            [$productId]
        );

        $statuses = [];

        foreach ($this->connection->getResults($sql) as $row) {
            $statuses[(int) $row['branch_id']] = ProductBranchStatus::from((string) $row['status']);
        }

        return $statuses;
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
