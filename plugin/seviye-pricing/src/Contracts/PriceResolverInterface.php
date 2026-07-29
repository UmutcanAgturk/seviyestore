<?php

declare(strict_types=1);

namespace Seviye\Pricing\Contracts;

/**
 * Published contract for resolving a product's actual price under this
 * platform's custom pricing engine - the boundary Seviye Commerce is meant
 * to depend on once it exists, never on Pricing's internal
 * Repository/Domain classes. See docs/ARCHITECTURE.md, "Kural" under the
 * layer diagram.
 *
 * Priority order: student-specific rule > branch-specific rule > general
 * (platform-wide) rule > $fallbackPrice (WooCommerce's own product price,
 * passed in by the caller rather than fetched here - this keeps Pricing
 * fully decoupled from WooCommerce/Seviye Commerce being installed at all,
 * and keeps it unit-testable without either).
 *
 * The product specification's priority chain also names a "bölge" (region)
 * tier between branch and general. It is intentionally not a separate,
 * resolvable tier here: no Region entity exists anywhere on the platform
 * yet (Seviye Branches' RBAC already treats Bölge Müdürü as full-HQ scope -
 * scp_manage_branches is shared with Genel Merkez, with no branch
 * partitioning), so a "regional price" would have no group of branches to
 * bind to. Building that grouping now, with no other module requiring it,
 * would be speculative; if/when a Region entity is introduced this
 * interface's implementation can add that tier without changing this
 * contract's signature.
 */
interface PriceResolverInterface
{
    public function resolve(int $productId, ?int $studentId, ?int $branchId, float $fallbackPrice): ResolvedPrice;
}
