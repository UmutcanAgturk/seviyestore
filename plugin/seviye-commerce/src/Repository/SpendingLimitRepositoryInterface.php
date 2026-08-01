<?php

declare(strict_types=1);

namespace Seviye\Commerce\Repository;

use Seviye\Commerce\Domain\SpendingLimit;
use Seviye\Commerce\Domain\SpendingLimitPeriod;

interface SpendingLimitRepositoryInterface
{
    /**
     * Absence of a row means "no limit" - see
     * {@see \Seviye\Commerce\Database\Migrations\CreateStudentSpendingLimitsTable}.
     */
    public function find(int $studentId): ?SpendingLimit;

    public function set(int $studentId, SpendingLimitPeriod $period, float $limitAmount): SpendingLimit;

    public function delete(int $studentId): void;
}
