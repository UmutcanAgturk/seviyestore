<?php

declare(strict_types=1);

namespace Seviye\Commerce\Repository;

use Seviye\Commerce\Domain\SpendingLimit;
use Seviye\Commerce\Domain\SpendingLimitPeriod;
use Seviye\Core\Database\ConnectionInterface;

final class WpdbSpendingLimitRepository implements SpendingLimitRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function find(int $studentId): ?SpendingLimit
    {
        $table = $this->connection->table('student_spending_limits');
        $sql = $this->connection->prepare(
            "SELECT period, limit_amount FROM {$table} WHERE student_id = %d LIMIT 1",
            [$studentId]
        );

        $rows = $this->connection->getResults($sql);

        if ($rows === []) {
            return null;
        }

        return new SpendingLimit(
            $studentId,
            SpendingLimitPeriod::from((string) $rows[0]['period']),
            (float) $rows[0]['limit_amount']
        );
    }

    public function set(int $studentId, SpendingLimitPeriod $period, float $limitAmount): SpendingLimit
    {
        $table = $this->connection->table('student_spending_limits');
        $now = $this->now();

        $sql = $this->connection->prepare(
            'INSERT INTO ' . $table . ' (student_id, period, limit_amount, created_at, updated_at) '
                . 'VALUES (%d, %s, %f, %s, %s) '
                . 'ON DUPLICATE KEY UPDATE period = VALUES(period), limit_amount = VALUES(limit_amount), '
                . 'updated_at = VALUES(updated_at)',
            [$studentId, $period->value, $limitAmount, $now, $now]
        );

        $this->connection->query($sql);

        return new SpendingLimit($studentId, $period, $limitAmount);
    }

    public function delete(int $studentId): void
    {
        $table = $this->connection->table('student_spending_limits');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE student_id = %d", [$studentId]);

        $this->connection->query($sql);
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
