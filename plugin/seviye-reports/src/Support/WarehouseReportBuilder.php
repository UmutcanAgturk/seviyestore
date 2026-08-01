<?php

declare(strict_types=1);

namespace Seviye\Reports\Support;

use Seviye\Depo\Contracts\PurchaseOrderReportRecord;
use Seviye\Reports\Domain\WarehouseReportRow;

/**
 * Pure grouping/summing - takes already-filtered records (supplier/date
 * filtering happens upstream, via Depo's own
 * Contracts\WarehouseReportQueryInterface::search(), never re-implemented
 * here) and a supplier name lookup, groups by supplier. Kept free of any
 * WordPress/WooCommerce call so it is fully unit-testable - the same split
 * SalesReportBuilder already follows.
 *
 * "Zamanında teslim oranı" (on-time delivery rate): among a supplier's
 * COMPLETED orders that ALSO have an expected_date, the share whose
 * completedAt date fell on or before expected_date. Orders without an
 * expected_date (nothing promised to compare against) and orders that
 * never completed are excluded from both the numerator and the
 * denominator - counting them as "late" would misrepresent a supplier who
 * was never given a deadline.
 */
final class WarehouseReportBuilder
{
    private const COMPLETED_STATUS = 'completed';

    /**
     * @param list<PurchaseOrderReportRecord> $records
     * @param array<int, string> $supplierNames
     * @return list<WarehouseReportRow>
     */
    public function build(array $records, array $supplierNames): array
    {
        $groups = [];

        foreach ($records as $record) {
            $key = $record->supplierId;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'supplierId' => $record->supplierId,
                    'orderCount' => 0,
                    'totalCost' => 0.0,
                    'completedOrderCount' => 0,
                    'onTimeOrderCount' => 0,
                    'timedOrderCount' => 0,
                ];
            }

            $groups[$key]['orderCount']++;
            $groups[$key]['totalCost'] += $record->totalCost;

            if ($record->status !== self::COMPLETED_STATUS) {
                continue;
            }

            $groups[$key]['completedOrderCount']++;

            if ($record->expectedDate === null || $record->completedAt === null) {
                continue;
            }

            $groups[$key]['timedOrderCount']++;

            if (substr($record->completedAt, 0, 10) <= $record->expectedDate) {
                $groups[$key]['onTimeOrderCount']++;
            }
        }

        return array_values(array_map(
            static fn (array $group): WarehouseReportRow => new WarehouseReportRow(
                $group['supplierId'],
                $supplierNames[$group['supplierId']] ?? '',
                $group['orderCount'],
                round($group['totalCost'], 2),
                $group['completedOrderCount'],
                $group['timedOrderCount'] > 0
                    ? round($group['onTimeOrderCount'] / $group['timedOrderCount'] * 100, 1)
                    : null
            ),
            $groups
        ));
    }
}
