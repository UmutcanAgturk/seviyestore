<?php

declare(strict_types=1);

namespace Seviye\Branches\Contracts;

/**
 * Minimal, stable read model for other modules to display alongside their
 * own data (e.g. a student's branch name) without depending on Branches'
 * full Domain\Branch entity (which carries Iban/CommissionRate value
 * objects those modules have no business knowing about).
 *
 * commissionRate is the one exception, exposed as a plain float rather than
 * Branches' own Domain\CommissionRate value object: other modules
 * legitimately need the raw number (e.g. Seviye Commerce snapshotting a
 * branch's commission rate on each order line item, for later hakediş
 * calculation) without needing that value object's own validation/behaviour.
 */
final class BranchSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly float $commissionRate
    ) {
    }
}
