<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Depo\Contracts\SupplierLookupInterface;
use Seviye\Depo\Contracts\SupplierSummary;

/**
 * A separate, minimal adapter rather than reusing {@see WpdbSupplierRepository}:
 * that class's find() returns the full Domain\Supplier (contact info, tax
 * number, status), which other modules have no business reading. Mirrors
 * Seviye\Branches\Repository\WpdbBranchLookup.
 */
final class WpdbSupplierLookup implements SupplierLookupInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function find(int $supplierId): ?SupplierSummary
    {
        $table = $this->connection->table('suppliers');
        $sql = $this->connection->prepare(
            "SELECT id, name FROM {$table} WHERE id = %d LIMIT 1",
            [$supplierId]
        );

        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            return null;
        }

        return new SupplierSummary((int) $rows[0]['id'], (string) $rows[0]['name']);
    }
}
