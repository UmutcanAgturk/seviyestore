<?php

declare(strict_types=1);

namespace Seviye\Pricing\Domain;

/**
 * A persisted price rule. Immutable snapshot returned by
 * {@see \Seviye\Pricing\Repository\PriceRuleRepositoryInterface} - callers
 * that want to change a rule call the repository again, they don't mutate
 * this object. productId refers to a WooCommerce product (wp_posts.ID,
 * post_type=product) - deliberately no FK, see docs/database/README.md for
 * why scp_* tables never reference wp_* tables.
 */
final class PriceRule
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly PriceScope $scope,
        public readonly Money $price,
        public readonly PriceRuleStatus $status
    ) {
    }
}
