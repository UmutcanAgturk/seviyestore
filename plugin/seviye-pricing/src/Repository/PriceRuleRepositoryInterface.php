<?php

declare(strict_types=1);

namespace Seviye\Pricing\Repository;

use Seviye\Pricing\Domain\Money;
use Seviye\Pricing\Domain\PriceRule;
use Seviye\Pricing\Domain\PriceRuleStatus;
use Seviye\Pricing\Domain\PriceScope;

interface PriceRuleRepositoryInterface
{
    public function create(int $productId, PriceScope $scope, Money $price): PriceRule;

    public function update(int $id, Money $price, PriceRuleStatus $status): PriceRule;

    public function find(int $id): ?PriceRule;

    public function delete(int $id): void;

    /**
     * Whether an ACTIVE rule already exists for this exact product+scope
     * target - callers check this before create() to avoid ambiguous
     * duplicate active rules (which scp_price_rules deliberately has no DB
     * constraint against, since MySQL unique indexes treat NULL columns as
     * distinct and can't express "at most one NULL" cleanly).
     */
    public function activeRuleExists(int $productId, PriceScope $scope): bool;

    /**
     * The ACTIVE rule matching this exact product+scope target, if any -
     * the building block {@see \Seviye\Pricing\Support\PriceResolver} calls
     * once per priority tier.
     */
    public function activeRuleFor(int $productId, PriceScope $scope): ?PriceRule;

    /**
     * @return list<PriceRule>
     */
    public function forProduct(int $productId): array;
}
