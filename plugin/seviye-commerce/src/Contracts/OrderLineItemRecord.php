<?php

declare(strict_types=1);

namespace Seviye\Commerce\Contracts;

/**
 * Read model for other modules querying order line item history (e.g.
 * Seviye Reports building a sales report) - mirrors
 * Seviye\Students\Contracts\StudentSummary's role: a stable, minimal
 * projection other modules depend on instead of Domain\OrderLineItem
 * directly. Unlike the internal Domain class, this carries `createdAt`:
 * a report genuinely needs "when", the same reasoning that made
 * Seviye\Finance\Domain\HakedisSettlement the platform's other documented
 * exception to "no raw timestamp in a Domain class".
 */
final class OrderLineItemRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $orderId,
        public readonly int $orderItemId,
        public readonly int $studentId,
        public readonly int $branchId,
        public readonly int $productId,
        public readonly float $commissionRate,
        public readonly float $price,
        public readonly float $vatAmount,
        public readonly string $status,
        public readonly string $createdAt
    ) {
    }
}
