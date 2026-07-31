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
}
