<?php

declare(strict_types=1);

namespace Seviye\Commerce\Repository;

use Seviye\Commerce\Domain\ProductBranchStatus;

interface ProductBranchVisibilityRepositoryInterface
{
    public function setStatus(int $productId, int $branchId, ProductBranchStatus $status): void;

    /**
     * Absence of a row means "active" - see
     * {@see \Seviye\Commerce\Database\Migrations\CreateProductBranchesTable}.
     */
    public function isActiveForBranch(int $productId, int $branchId): bool;

    /**
     * @return array<int, ProductBranchStatus> keyed by branch_id, only for
     *     branches that have an EXPLICIT row (a branch absent from this map
     *     is implicitly active, per {@see isActiveForBranch()}).
     */
    public function statusesForProduct(int $productId): array;
}
