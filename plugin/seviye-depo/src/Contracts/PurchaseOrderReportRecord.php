<?php

declare(strict_types=1);

namespace Seviye\Depo\Contracts;

/**
 * Read model for other modules reporting on purchase order history (Seviye
 * Reports' "Depo Raporları") - mirrors Commerce's OrderLineItemRecord's
 * role: a stable, minimal projection other modules depend on instead of
 * Domain\PurchaseOrder directly. `totalCost` is pre-aggregated here
 * (sum of quantity_ordered * unit_cost across the order's items, items
 * with a null unit_cost contribute 0) so report builders never need to
 * know about scp_purchase_order_items at all. `completedAt` is
 * scp_purchase_orders.updated_at, but ONLY when status is completed - see
 * WpdbWarehouseReportQuery's hydrate() - the same "no raw timestamp
 * unless a report genuinely needs it" reasoning as OrderLineItemRecord's
 * own docblock.
 *
 * `branchId` (Faz 4) mirrors scp_purchase_orders.branch_id verbatim - null
 * means the order was placed for Genel Merkez's own depo, an int means it
 * was placed for that branch's own depo. Frozen at order-creation time,
 * same as the column itself - see CreatePurchaseOrdersTable's docblock.
 */
final class PurchaseOrderReportRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $supplierId,
        public readonly string $status,
        public readonly ?string $expectedDate,
        public readonly ?string $completedAt,
        public readonly float $totalCost,
        public readonly string $createdAt,
        public readonly ?int $branchId = null
    ) {
    }
}
