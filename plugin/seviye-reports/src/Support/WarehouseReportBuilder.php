<?php

declare(strict_types=1);

namespace Seviye\Reports\Support;

use Seviye\Depo\Contracts\PurchaseOrderReportRecord;
use Seviye\Reports\Domain\WarehouseReportRow;

/**
 * Pure grouping/summing - takes already-filtered records (supplier/date/
 * depo filtering happens upstream, via Depo's own
 * Contracts\WarehouseReportQueryInterface::search(), never re-implemented
 * here) and a supplier name lookup, groups by (supplier, depo). Kept free
 * of any WordPress/WooCommerce call so it is fully unit-testable - the
 * same split SalesReportBuilder already follows.
 *
 * Faz 4: grouping key is (supplierId, branchId) rather than supplierId
 * alone - a supplier's directory entry is shared/platform-wide, but a
 * supplier can receive orders from BOTH Genel Merkez's own depo and one or
 * more branches' own depos, and those purchase histories must stay
 * separate (mirrors the same reasoning WarehouseReportRow's own docblock
 * gives).
 *
 * "Zamanında teslim oranı" (on-time delivery rate): among a group's
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
     * @param array<int, string> $branchNames keyed by branchId - the Genel
     *     Merkez group (branchId null) never looks this array up
     * @return list<WarehouseReportRow>
     */
    public function build(array $records, array $supplierNames, array $branchNames = []): array
    {
        $groups = [];

        foreach ($records as $record) {
            $key = $record->supplierId . '|' . ($record->branchId ?? 'hq');

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'supplierId' => $record->supplierId,
                    'branchId' => $record->branchId,
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

        $hqLabel = function_exists('__') ? __('Genel Merkez', 'seviye-reports') : 'Genel Merkez';

        return array_values(array_map(
            static fn (array $group): WarehouseReportRow => new WarehouseReportRow(
                $group['supplierId'],
                $supplierNames[$group['supplierId']] ?? '',
                $group['orderCount'],
                round($group['totalCost'], 2),
                $group['completedOrderCount'],
                $group['timedOrderCount'] > 0
                    ? round($group['onTimeOrderCount'] / $group['timedOrderCount'] * 100, 1)
                    : null,
                $group['branchId'],
                $group['branchId'] === null ? $hqLabel : ($branchNames[$group['branchId']] ?? '')
            ),
            $groups
        ));
    }
}
