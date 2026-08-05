<?php

declare(strict_types=1);

namespace Seviye\Commerce\Rbac;

/**
 * Mirrors Seviye\Reports\Rbac\ReportCapability's VIEW_REPORTS/
 * VIEW_OWN_REPORTS split exactly: HQ roles may browse any branch's orders,
 * Şube Müdürü only orders touching their own branch's students. See
 * Http\AdminOrdersRestController.
 */
enum OrderCapability: string
{
    /** Genel Merkez / Bölge Müdürü: every order, platform-wide. */
    case VIEW_ORDERS = 'scp_view_orders';

    /** Şube Müdürü: only orders touching their own branch. */
    case VIEW_OWN_BRANCH_ORDERS = 'scp_view_own_branch_orders';

    /**
     * Genel Merkez / Bölge Müdürü: cancel any not-yet-paid order (no money
     * moves - see AdminOrdersRestController::cancel()).
     */
    case CANCEL_ORDERS = 'scp_cancel_orders';

    /** Şube Müdürü: cancel only orders touching their own branch. */
    case CANCEL_OWN_BRANCH_ORDERS = 'scp_cancel_own_branch_orders';

    /**
     * Genel Merkez / Bölge Müdürü / Muhasebe only - unlike CANCEL_ORDERS,
     * deliberately has no branch-scoped tier: a refund moves real money back
     * out (see AdminOrdersRestController::refund()), the same HQ-only
     * restriction Seviye\Finance\Rbac\HakedisCapability::RECORD_SETTLEMENT
     * already applies to the platform's other money-moving action.
     */
    case REFUND_ORDERS = 'scp_refund_orders';

    /**
     * "Kargoya verildi/teslim edildi" - Genel Merkez / Bölge Müdürü: mark
     * any order shipped/delivered (see AdminOrdersRestController::ship()/
     * deliver()). Same VIEW_ORDERS/CANCEL_ORDERS split reasoning: this is a
     * logistics update, not a money movement, so (unlike REFUND_ORDERS) it
     * DOES have a branch-scoped tier below.
     */
    case UPDATE_ORDER_FULFILLMENT = 'scp_update_order_fulfillment';

    /** Şube Müdürü: mark shipped/delivered only for orders touching their own branch. */
    case UPDATE_OWN_BRANCH_ORDER_FULFILLMENT = 'scp_update_own_branch_order_fulfillment';
}
