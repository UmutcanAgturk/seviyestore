<?php

declare(strict_types=1);

namespace Seviye\Finance\Repository;

use Seviye\Finance\Domain\HakedisEntry;
use Seviye\Finance\Domain\HakedisEntryType;

interface HakedisRepositoryInterface
{
    public function record(
        int $branchId,
        int $orderId,
        int $orderItemId,
        int $studentId,
        float $amount,
        float $commissionRate,
        float $price,
        float $vatAmount,
        HakedisEntryType $type
    ): HakedisEntry;

    public function entryExists(int $orderId, int $orderItemId, HakedisEntryType $type): bool;

    /**
     * Sum of every entry's signed amount for a branch - always up to date,
     * never a separately-maintained running total that could drift.
     */
    public function balanceForBranch(int $branchId): float;
}
