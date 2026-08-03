<?php

declare(strict_types=1);

namespace Seviye\Finance\Tests\Fakes;

use Seviye\Finance\Domain\HakedisEntry;
use Seviye\Finance\Domain\HakedisEntryType;
use Seviye\Finance\Repository\HakedisRepositoryInterface;

final class FakeHakedisRepository implements HakedisRepositoryInterface
{
    /** @var list<HakedisEntry> */
    public array $entries = [];

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
    ): HakedisEntry {
        $entry = new HakedisEntry(
            count($this->entries) + 1,
            $branchId,
            $orderId,
            $orderItemId,
            $studentId,
            $amount,
            $commissionRate,
            $price,
            $vatAmount,
            $type,
            $refundId
        );

        $this->entries[] = $entry;

        return $entry;
    }

    public function entryExists(int $orderId, int $orderItemId, HakedisEntryType $type, ?int $refundId = null): bool
    {
        foreach ($this->entries as $entry) {
            if (
                $entry->orderId === $orderId
                && $entry->orderItemId === $orderItemId
                && $entry->type === $type
                && $entry->refundId === $refundId
            ) {
                return true;
            }
        }

        return false;
    }

    public function balanceForBranch(int $branchId): float
    {
        $total = 0.0;

        foreach ($this->entries as $entry) {
            if ($entry->branchId === $branchId) {
                $total += $entry->amount;
            }
        }

        return $total;
    }
}
