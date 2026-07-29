<?php

declare(strict_types=1);

namespace Seviye\Pricing\Support;

use Seviye\Pricing\Contracts\PriceResolverInterface;
use Seviye\Pricing\Contracts\PriceSource;
use Seviye\Pricing\Contracts\ResolvedPrice;
use Seviye\Pricing\Domain\PriceScope;
use Seviye\Pricing\Repository\PriceRuleRepositoryInterface;
use Seviye\Students\Contracts\StudentLookupInterface;

final class PriceResolver implements PriceResolverInterface
{
    public function __construct(
        private readonly PriceRuleRepositoryInterface $rules,
        private readonly StudentLookupInterface $students
    ) {
    }

    public function resolve(int $productId, ?int $studentId, ?int $branchId, float $fallbackPrice): ResolvedPrice
    {
        if ($studentId !== null) {
            $rule = $this->rules->activeRuleFor($productId, PriceScope::forStudent($studentId));

            if ($rule !== null) {
                return new ResolvedPrice($rule->price->toFloat(), PriceSource::STUDENT);
            }
        }

        $resolvedBranchId = $branchId ?? $this->branchIdForStudent($studentId);

        if ($resolvedBranchId !== null) {
            $rule = $this->rules->activeRuleFor($productId, PriceScope::forBranch($resolvedBranchId));

            if ($rule !== null) {
                return new ResolvedPrice($rule->price->toFloat(), PriceSource::BRANCH);
            }
        }

        $rule = $this->rules->activeRuleFor($productId, PriceScope::general());

        if ($rule !== null) {
            return new ResolvedPrice($rule->price->toFloat(), PriceSource::GENERAL);
        }

        return new ResolvedPrice($fallbackPrice, PriceSource::FALLBACK);
    }

    private function branchIdForStudent(?int $studentId): ?int
    {
        if ($studentId === null) {
            return null;
        }

        return $this->students->find($studentId)?->branchId;
    }
}
