<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Repository;

use Seviye\SubeSiparis\Domain\BranchOrderQuota;

interface BranchOrderQuotaRepositoryInterface
{
    /**
     * Creates or replaces the free-quantity quota for a (branch, product)
     * pair - idempotent, safe to call repeatedly with the same values.
     */
    public function upsert(int $branchId, int $productId, int $freeQuantity): BranchOrderQuota;

    /**
     * Null return means no quota row exists for this pair - callers must
     * treat that the same as freeQuantity = 0 (see Domain\BranchOrderQuota's
     * own docblock), not as an error.
     */
    public function find(int $branchId, int $productId): ?BranchOrderQuota;

    /**
     * @return list<BranchOrderQuota>
     */
    public function all(?int $branchId = null): array;

    public function delete(int $id): void;
}
