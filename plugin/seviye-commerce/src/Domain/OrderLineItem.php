<?php

declare(strict_types=1);

namespace Seviye\Commerce\Domain;

/**
 * A persisted snapshot of one WooCommerce order line item's
 * platform-specific data - which student/branch it was for, what
 * commission rate applied, and what was actually charged, all captured at
 * order-creation time so a later hakediş calculation never depends on
 * Branches' *current* commission rate for a historical order.
 *
 * `status` deliberately stays a plain string mirroring WooCommerce's own
 * order status slug (`wc_get_order_statuses()` is an open, extensible
 * dictionary - third-party payment/subscription plugins add their own
 * statuses) rather than a closed PHP enum, which would go stale the moment
 * such a plugin is installed.
 */
final class OrderLineItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $orderId,
        public readonly int $orderItemId,
        public readonly int $studentId,
        public readonly int $branchId,
        public readonly float $commissionRate,
        public readonly float $price,
        public readonly string $status
    ) {
    }
}
