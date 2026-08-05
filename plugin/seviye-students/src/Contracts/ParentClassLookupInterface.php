<?php

declare(strict_types=1);

namespace Seviye\Students\Contracts;

/**
 * Published contract: which grade-level labels (class_name, e.g. "5. Sınıf")
 * a Veli's own children are currently in - the boundary other modules are
 * allowed to depend on (e.g. Seviye Commerce deciding whether a product
 * restricted to certain grade levels is visible to a given Veli, by
 * checking their children's class names against the product's
 * grade_levels list), never on Students' internal Repository classes. See
 * docs/ARCHITECTURE.md, "Kural" under the layer diagram. Mirrors
 * ParentBranchLookupInterface exactly, one field over.
 */
interface ParentClassLookupInterface
{
    /**
     * @return list<string> distinct class_name values across this parent's
     *     linked children - empty if the parent has no linked children at
     *     all.
     */
    public function classNamesForParent(int $parentUserId): array;
}
