<?php

declare(strict_types=1);

namespace Seviye\Students\Contracts;

/**
 * Published contract: the reverse of {@see ParentBranchLookupInterface} -
 * which veli (parent) WP user ids have at least one child in a given
 * branch, or platform-wide. The boundary other modules are allowed to
 * depend on (e.g. Seviye Notifications resolving recipients for a "Toplu
 * Duyuru" broadcast), never on Students' internal Repository classes. See
 * docs/ARCHITECTURE.md, "Kural" under the layer diagram.
 */
interface BranchParentLookupInterface
{
    /**
     * @return list<int> distinct veli user ids with at least one linked
     *     child in this branch - empty if the branch has no students with
     *     a linked veli.
     */
    public function parentUserIdsForBranch(int $branchId): array;

    /**
     * @return list<int> every distinct veli user id linked to at least one
     *     student, across every branch.
     */
    public function allParentUserIds(): array;
}
