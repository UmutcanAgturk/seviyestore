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
        HakedisEntryType $type,
        ?int $refundId = null
    ): HakedisEntry;

    /**
     * $refundId distinguishes repeated PARTIAL_REVERSAL entries for the
     * same order item (one per distinct refund) - see
     * Domain\HakedisEntryType::PARTIAL_REVERSAL. Always null for
     * EARNED/REVERSED, whose idempotency check never needs it.
     */
    public function entryExists(int $orderId, int $orderItemId, HakedisEntryType $type, ?int $refundId = null): bool;

    /**
     * Sum of every entry's signed amount for a branch - always up to date,
     * never a separately-maintained running total that could drift.
     */
    public function balanceForBranch(int $branchId): float;
}
