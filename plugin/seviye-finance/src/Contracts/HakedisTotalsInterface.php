<?php

declare(strict_types=1);

namespace Seviye\Finance\Contracts;

/**
 * Published contract for other modules that need a platform-WIDE hakediş
 * figure (currently only Seviye Notifications' weekly digest) - distinct
 * from HakedisRepositoryInterface::balanceForBranch()/SettlementRepositoryInterface::settledForBranch(),
 * which are per-branch and used by HakedisRestController's panel-facing
 * balance views. Mirrors Seviye\Depo\Contracts\WarehouseReportQueryInterface's
 * role: the boundary other modules are allowed to depend on.
 */
interface HakedisTotalsInterface
{
    /** Sum of every branch's (accrued - settled), i.e. the platform's total outstanding hakediş. */
    public function totalOutstandingBalance(): float;
}
