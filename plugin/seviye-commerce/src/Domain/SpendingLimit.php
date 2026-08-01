<?php

declare(strict_types=1);

namespace Seviye\Commerce\Domain;

final class SpendingLimit
{
    public function __construct(
        public readonly int $studentId,
        public readonly SpendingLimitPeriod $period,
        public readonly float $limitAmount
    ) {
    }
}
