<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\SubeSiparis\Domain\BranchOrderQuota;

final class WpdbBranchOrderQuotaRepository implements BranchOrderQuotaRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * A single UPSERT (rather than a SELECT-then-INSERT-or-UPDATE) relies on
     * scp_branch_order_quotas' own UNIQUE KEY (branch_id, product_id) to
     * make this atomic and race-free under concurrent writers - same
     * pattern as Core's WpdbSettingsRepository::set().
     */
    public function upsert(int $branchId, int $productId, int $freeQuantity): BranchOrderQuota
    {
        $table = $this->connection->table('branch_order_quotas');
        $now = $this->now();

        $sql = $this->connection->prepare(
            "INSERT INTO {$table} (branch_id, product_id, free_quantity, created_at, updated_at)
             VALUES (%d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE free_quantity = VALUES(free_quantity), updated_at = VALUES(updated_at)",
            [$branchId, $productId, $freeQuantity, $now, $now]
        );

        $this->connection->query($sql);

        $quota = $this->find($branchId, $productId);

        if ($quota === null) {
            throw new RuntimeException('Kota kaydedildikten sonra okunamadı.');
        }

        return $quota;
    }

    public function find(int $branchId, int $productId): ?BranchOrderQuota
    {
        $table = $this->connection->table('branch_order_quotas');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE branch_id = %d AND product_id = %d LIMIT 1",
            [$branchId, $productId]
        );

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function all(?int $branchId = null): array
    {
        $table = $this->connection->table('branch_order_quotas');

        if ($branchId !== null) {
            $sql = $this->connection->prepare(
                "SELECT * FROM {$table} WHERE branch_id = %d ORDER BY id DESC",
                [$branchId]
            );
        } else {
            $sql = "SELECT * FROM {$table} ORDER BY id DESC";
        }

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    public function delete(int $id): void
    {
        $table = $this->connection->table('branch_order_quotas');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE id = %d", [$id]);

        $this->connection->query($sql);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): BranchOrderQuota
    {
        return new BranchOrderQuota(
            (int) $row['id'],
            (int) $row['branch_id'],
            (int) $row['product_id'],
            (int) $row['free_quantity'],
            (string) $row['created_at'],
            (string) $row['updated_at']
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
