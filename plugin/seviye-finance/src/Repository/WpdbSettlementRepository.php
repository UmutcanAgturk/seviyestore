<?php

declare(strict_types=1);

namespace Seviye\Finance\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Finance\Domain\HakedisSettlement;
use Seviye\Finance\Domain\SettlementMethod;

final class WpdbSettlementRepository implements SettlementRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function record(
        int $branchId,
        float $amount,
        SettlementMethod $method,
        ?string $note,
        int $recordedByUserId
    ): HakedisSettlement {
        $this->connection->insert($this->connection->table('hakedis_settlements'), [
            'branch_id' => $branchId,
            'amount' => $amount,
            'method' => $method->value,
            'note' => $note,
            'recorded_by' => $recordedByUserId,
            'created_at' => $this->now(),
        ]);

        $table = $this->connection->table('hakedis_settlements');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            [$this->connection->lastInsertId()]
        );
        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            throw new RuntimeException('Hakediş settlement could not be read back after insert.');
        }

        return $this->hydrate($rows[0]);
    }

    public function listForBranch(int $branchId): array
    {
        $table = $this->connection->table('hakedis_settlements');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE branch_id = %d ORDER BY id DESC",
            [$branchId]
        );

        $rows = $this->connection->getResults($sql);

        return array_map($this->hydrate(...), $rows);
    }

    public function settledForBranch(int $branchId): float
    {
        $table = $this->connection->table('hakedis_settlements');
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
    private function hydrate(array $row): HakedisSettlement
    {
        return new HakedisSettlement(
            (int) $row['id'],
            (int) $row['branch_id'],
            (float) $row['amount'],
            SettlementMethod::from((string) $row['method']),
            $row['note'] !== null ? (string) $row['note'] : null,
            (int) $row['recorded_by'],
            (string) $row['created_at']
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
