<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Domain;

final class BranchOrder
{
    /** @param list<BranchOrderItem> $items */
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly BranchOrderStatus $status,
        public readonly int $createdByUserId,
        public readonly ?string $note,
        public readonly string $createdAt,
        public readonly array $items,
        public readonly ?string $submittedAt = null,
        public readonly ?int $approvedByUserId = null,
        public readonly ?string $approvedAt = null,
        public readonly ?string $rejectedReason = null,
        public readonly ?int $wcOrderId = null
    ) {
    }

    public function hasPaidPortion(): bool
    {
        foreach ($this->items as $item) {
            if ($item->hasPaidPortion()) {
                return true;
            }
        }

        return false;
    }

    public function totalPaidAmount(): float
    {
        $total = 0.0;

        foreach ($this->items as $item) {
            $total += $item->paidAmount();
        }

        return round($total, 2);
    }
}
