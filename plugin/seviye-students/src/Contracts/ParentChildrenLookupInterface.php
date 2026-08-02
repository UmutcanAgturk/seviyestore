<?php

declare(strict_types=1);

namespace Seviye\Students\Contracts;

/**
 * Published contract: every child linked to a given veli (parent) WP user -
 * the boundary other modules are allowed to depend on, never on Students'
 * internal Repository\StudentParentRepositoryInterface/StudentRepositoryInterface.
 * First consumer: Seviye Security's KVKK veri ihracı (privacy export),
 * which needs "what does the platform hold about this account" without
 * depending on Students' internal Domain\Student. Mirrors
 * {@see BranchParentLookupInterface}'s "which parents" shape, one level
 * more specific ("which children of THIS parent").
 */
interface ParentChildrenLookupInterface
{
    /**
     * @return list<StudentSummary> empty if this user has no linked
     *     children (including: is not a veli at all)
     */
    public function childrenOf(int $parentUserId): array;
}
