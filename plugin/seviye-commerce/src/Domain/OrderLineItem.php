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
 * `vatAmount` is WooCommerce's own tax calculation for this line item
 * (`WC_Order_Item_Product::get_total_tax()`) - Seviye never recomputes VAT
 * itself, only snapshots what WooCommerce's tax engine already determined,
 * the same "don't re-derive what was actually charged" principle `price`
 * already follows. Only the amount is stored, not a rate: a rate would be
 * a derived value (vatAmount / price) that could drift from what's stored,
 * not an independent fact captured at order time.
 *
 * `productId` is WooCommerce's own post ID for the purchased product
 * (`WC_Order_Item_Product::get_product_id()`) - no FK (this platform never
 * puts FKs on WordPress/WooCommerce core tables), captured purely so Seviye
 * Reports can group by product/category without re-deriving it from raw WC
 * order item meta. Category is deliberately NOT snapshotted alongside it:
 * unlike commission_rate/price (facts about what actually happened at
 * order time), a product's category assignment is current-state
 * information a report can resolve live from WooCommerce's own taxonomy at
 * report-generation time.
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
        public readonly int $productId,
        public readonly float $commissionRate,
        public readonly float $price,
        public readonly float $vatAmount,
        public readonly string $status
    ) {
    }
}
