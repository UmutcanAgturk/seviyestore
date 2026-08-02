<?php

declare(strict_types=1);

namespace Seviye\Finance\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Finance\Contracts\HakedisTotalsInterface;

/**
 * A separate, minimal adapter rather than reusing
 * {@see WpdbHakedisRepository}/{@see WpdbSettlementRepository}: those
 * classes' sum methods are per-branch (WHERE branch_id = %d), the exact
 * opposite of what a platform-wide total needs. Mirrors
 * Seviye\Depo\Repository\WpdbSupplierLookup's "separate adapter, not a
 * second responsibility bolted onto the domain repository" reasoning.
 */
final class WpdbHakedisTotals implements HakedisTotalsInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function totalOutstandingBalance(): float
    {
        return $this->sum('hakedis_entries', 'amount') - $this->sum('hakedis_settlements', 'amount');
    }

    private function sum(string $tableSuffix, string $column): float
    {
        $table = $this->connection->table($tableSuffix);
        $rows = $this->connection->getResults("SELECT SUM({$column}) AS total FROM {$table}");

        if (!isset($rows[0]['total']) || $rows[0]['total'] === null) {
            return 0.0;
        }

        return (float) $rows[0]['total'];
    }
}
