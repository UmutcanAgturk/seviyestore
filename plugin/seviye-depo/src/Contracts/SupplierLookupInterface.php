<?php

declare(strict_types=1);

namespace Seviye\Depo\Contracts;

/**
 * Published contract for read-only supplier lookups by other modules -
 * mirrors Seviye\Branches\Contracts\BranchLookupInterface exactly.
 */
interface SupplierLookupInterface
{
    public function find(int $supplierId): ?SupplierSummary;
}
