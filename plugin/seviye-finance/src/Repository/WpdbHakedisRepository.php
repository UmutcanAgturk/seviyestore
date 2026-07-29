<?php

declare(strict_types=1);

namespace Seviye\Finance\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Finance\Domain\HakedisEntry;
use Seviye\Finance\Domain\HakedisEntryType;

final class WpdbHakedisRepository implements HakedisRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function record(
        int $branchId,
        int $orderId,
        int $orderItemId,
        int $studentId,
        float $amount,
        float $commissionRate,
        float $price,
        HakedisEntryType $type
    ): HakedisEntry {
        $this->connection->insert($this->connection->table('hakedis_entries'), [
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'student_id' => $studentId,
            'amount' => $amount,
            'commission_rate' => $commissionRate,
            'price' => $price,
            'type' => $type->value,
            'created_at' => $this->now(),
        ]);

        $table = $this->connection->table('hakedis_entries');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            [$this->connection->lastInsertId()]
        );
        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            throw new RuntimeException('Hakediş entry could not be read back after insert.');
        }

        return $this->hydrate($rows[0]);
    }

    public function entryExists(int $orderId, int $orderItemId, HakedisEntryType $type): bool
    {
        $table = $this->connection->table('hakedis_entries');
        $sql = $this->connection->prepare(
            "SELECT id FROM {$table} WHERE order_id = %d AND order_item_id = %d AND type = %s LIMIT 1",
            [$orderId, $orderItemId, $type->value]
        );

        return $this->connection->getResults($sql) !== [];
    }

    public function balanceForBranch(int $branchId): float
    {
        $table = $this->connection->table('hakedis_entries');
        $sql = $this->connection->prepare(
            "SELECT SUM(amount) AS total FROM {$table} WHERE branch_id = %d",
            [$branchId]
        );

        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0]['total']) || $rows[0]['total'] === null) {
            return 0.0;
        }

        return (float) $rows[0]['total'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): HakedisEntry
    {
        return new HakedisEntry(
            (int) $row['id'],
            (int) $row['branch_id'],
            (int) $row['order_id'],
            (int) $row['order_item_id'],
            (int) $row['student_id'],
            (float) $row['amount'],
            (float) $row['commission_rate'],
            (float) $row['price'],
            HakedisEntryType::from((string) $row['type'])
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
