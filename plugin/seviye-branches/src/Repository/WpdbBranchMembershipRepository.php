<?php

declare(strict_types=1);

namespace Seviye\Branches\Repository;

use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Database\ConnectionInterface;

final class WpdbBranchMembershipRepository implements BranchMembershipInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function assign(int $userId, int $branchId): void
    {
        $table = $this->connection->table('branch_users');

        if ($this->branchIdForUser($userId) !== null) {
            $sql = $this->connection->prepare(
                "UPDATE {$table} SET branch_id = %d WHERE user_id = %d",
                [$branchId, $userId]
            );
            $this->connection->query($sql);

            return;
        }

        $this->connection->insert($table, [
            'branch_id' => $branchId,
            'user_id' => $userId,
            'created_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function branchIdForUser(int $userId): ?int
    {
        $table = $this->connection->table('branch_users');
        $sql = $this->connection->prepare("SELECT branch_id FROM {$table} WHERE user_id = %d LIMIT 1", [$userId]);

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]['branch_id']) ? (int) $rows[0]['branch_id'] : null;
    }

    public function unassign(int $userId): void
    {
        $table = $this->connection->table('branch_users');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE user_id = %d", [$userId]);

        $this->connection->query($sql);
    }

    public function usersForBranch(int $branchId): array
    {
        $table = $this->connection->table('branch_users');
        $sql = $this->connection->prepare("SELECT user_id FROM {$table} WHERE branch_id = %d", [$branchId]);

        $rows = $this->connection->getResults($sql);

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }
}
