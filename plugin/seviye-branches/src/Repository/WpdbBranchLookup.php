<?php

declare(strict_types=1);

namespace Seviye\Branches\Repository;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchSummary;
use Seviye\Core\Database\ConnectionInterface;

/**
 * A separate, minimal adapter rather than reusing {@see WpdbBranchRepository}:
 * that class's find() returns the full Domain\Branch (with Iban/CommissionRate
 * value objects), which would collide on method name/return type with this
 * interface's lighter {@see BranchSummary} and pulls in data other modules
 * have no business reading.
 */
final class WpdbBranchLookup implements BranchLookupInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function find(int $branchId): ?BranchSummary
    {
        $table = $this->connection->table('branches');
        $sql = $this->connection->prepare(
            'SELECT id, name, slug, commission_rate FROM ' . $table . ' WHERE id = %d LIMIT 1',
            [$branchId]
        );

        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            return null;
        }

        return new BranchSummary(
            (int) $rows[0]['id'],
            (string) $rows[0]['name'],
            (string) $rows[0]['slug'],
            (float) $rows[0]['commission_rate']
        );
    }

    public function exists(int $branchId): bool
    {
        return $this->find($branchId) !== null;
    }
}
