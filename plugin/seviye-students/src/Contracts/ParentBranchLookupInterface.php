<?php

declare(strict_types=1);

namespace Seviye\Students\Contracts;

/**
 * Published contract: which branches a Veli's own children belong to - the
 * boundary other modules are allowed to depend on (e.g. Seviye Commerce
 * deciding whether a shared-catalog product is active for a given Veli, by
 * checking their children's branches against scp_product_branches), never
 * on Students' internal Repository classes. See docs/ARCHITECTURE.md,
 * "Kural" under the layer diagram.
 */
interface ParentBranchLookupInterface
{
    /**
     * @return list<int> distinct branch ids, one per branch that has at
     *     least one of this parent's linked children - empty if the parent
     *     has no linked children at all.
     */
    public function branchIdsForParent(int $parentUserId): array;
}
