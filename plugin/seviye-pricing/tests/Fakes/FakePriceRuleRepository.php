<?php

declare(strict_types=1);

namespace Seviye\Pricing\Tests\Fakes;

use RuntimeException;
use Seviye\Pricing\Domain\Money;
use Seviye\Pricing\Domain\PriceRule;
use Seviye\Pricing\Domain\PriceRuleStatus;
use Seviye\Pricing\Domain\PriceScope;
use Seviye\Pricing\Domain\PriceScopeType;
use Seviye\Pricing\Repository\PriceRuleRepositoryInterface;

final class FakePriceRuleRepository implements PriceRuleRepositoryInterface
{
    /** @var array<string, PriceRule> */
    public array $activeRules = [];

    public function create(int $productId, PriceScope $scope, Money $price): PriceRule
    {
        throw new RuntimeException('Not used by these tests.');
    }

    public function update(int $id, Money $price, PriceRuleStatus $status): PriceRule
    {
        throw new RuntimeException('Not used by these tests.');
    }

    public function find(int $id): ?PriceRule
    {
        return null;
    }

    public function delete(int $id): void
    {
    }

    public function activeRuleExists(int $productId, PriceScope $scope): bool
    {
        return $this->activeRuleFor($productId, $scope) !== null;
    }

    public function activeRuleFor(int $productId, PriceScope $scope): ?PriceRule
    {
        return $this->activeRules[$this->key($productId, $scope)] ?? null;
    }

    public function forProduct(int $productId): array
    {
        return [];
    }

    public function put(int $productId, PriceScope $scope, float $price): void
    {
        $this->activeRules[$this->key($productId, $scope)] = new PriceRule(
            count($this->activeRules) + 1,
            $productId,
            $scope,
            Money::fromFloat($price),
            PriceRuleStatus::ACTIVE
        );
    }

    private function key(int $productId, PriceScope $scope): string
    {
        return match ($scope->type) {
            PriceScopeType::STUDENT => $productId . ':student:' . $scope->studentId,
            PriceScopeType::BRANCH => $productId . ':branch:' . $scope->branchId,
            PriceScopeType::GENERAL => $productId . ':general',
        };
    }
}
