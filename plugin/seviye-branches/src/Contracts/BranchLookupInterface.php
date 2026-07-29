<?php

declare(strict_types=1);

namespace Seviye\Branches\Contracts;

/**
 * Published contract for read-only branch lookups by other modules.
 */
interface BranchLookupInterface
{
    public function find(int $branchId): ?BranchSummary;

    public function exists(int $branchId): bool;
}
