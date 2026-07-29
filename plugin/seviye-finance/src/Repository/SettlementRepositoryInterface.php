<?php

declare(strict_types=1);

namespace Seviye\Finance\Repository;

use Seviye\Finance\Domain\HakedisSettlement;
use Seviye\Finance\Domain\SettlementMethod;

interface SettlementRepositoryInterface
{
    public function record(
        int $branchId,
        float $amount,
        SettlementMethod $method,
        ?string $note,
        int $recordedByUserId
    ): HakedisSettlement;

    /**
     * @return list<HakedisSettlement>
     */
    public function listForBranch(int $branchId): array;

    /**
     * Sum of every settlement's amount for a branch - the counterpart to
     * HakedisRepositoryInterface::balanceForBranch(), together forming the
     * branch's outstanding balance (accrued - settled).
     */
    public function settledForBranch(int $branchId): float;
}
