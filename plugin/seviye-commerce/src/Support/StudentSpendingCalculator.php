<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

use Seviye\Commerce\Domain\SpendingLimitPeriod;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Reads wc_get_orders() directly (not scp_order_line_items) to sum a
 * student's paid spend, same "read WC directly" principle as
 * AdminOrdersRestController and Reports' OverviewRestController - see their
 * docblocks. Only orders in a paid status (wc_get_is_paid_statuses(), e.g.
 * processing/completed) count; a pending/failed/cancelled order was never
 * actually paid for and must not count against a student's limit.
 */
final class StudentSpendingCalculator
{
    private const STUDENT_META_KEY = '_scp_student_id';

    public function spentAmount(int $studentId, SpendingLimitPeriod $period): float
    {
        if (!function_exists('wc_get_orders')) {
            return 0.0;
        }

        $monthStart = $period === SpendingLimitPeriod::MONTHLY ? gmdate('Y-m-01') : null;
        $total = 0.0;

        foreach (wc_get_orders(['limit' => -1, 'status' => $this->paidStatuses()]) as $order) {
            if (!$order instanceof WC_Order || !$this->withinPeriod($order, $monthStart)) {
                continue;
            }

            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }

                if ((int) $item->get_meta(self::STUDENT_META_KEY) === $studentId) {
                    $total += (float) $item->get_total();
                }
            }
        }

        return $total;
    }

    private function withinPeriod(WC_Order $order, ?string $monthStart): bool
    {
        if ($monthStart === null) {
            return true;
        }

        $createdAt = $order->get_date_created();

        return $createdAt !== null && $createdAt->date('Y-m-d') >= $monthStart;
    }

    /**
     * @return list<string>
     */
    private function paidStatuses(): array
    {
        return function_exists('wc_get_is_paid_statuses') ? wc_get_is_paid_statuses() : ['processing', 'completed'];
    }
}
