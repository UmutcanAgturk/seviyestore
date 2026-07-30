<?php

declare(strict_types=1);

namespace Seviye\Reports\Support;

use Seviye\Commerce\Contracts\OrderLineItemRecord;
use Seviye\Reports\Domain\SalesReportRow;

/**
 * Pure grouping/summing - takes already-filtered records (branch/product/
 * date filtering happens upstream, via Commerce's own
 * Contracts\OrderLineItemQueryInterface::search(), never re-implemented
 * here) and a name lookup for display, groups by branch+product. Kept free
 * of any WordPress/WooCommerce call so it is fully unit-testable, the same
 * "Support classes stay pure, Http classes touch the platform" split every
 * other module in this codebase follows.
 */
final class SalesReportBuilder
{
    /**
     * @param list<OrderLineItemRecord> $records
     * @param array<int, string> $branchNames
     * @param array<int, string> $productNames
     * @return list<SalesReportRow>
     */
    public function build(array $records, array $branchNames, array $productNames): array
    {
        $groups = [];

        foreach ($records as $record) {
            $key = $record->branchId . ':' . $record->productId;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'branchId' => $record->branchId,
                    'productId' => $record->productId,
                    'orderCount' => 0,
                    'totalPrice' => 0.0,
                    'totalVat' => 0.0,
                ];
            }

            $groups[$key]['orderCount']++;
            $groups[$key]['totalPrice'] += $record->price;
            $groups[$key]['totalVat'] += $record->vatAmount;
        }

        return array_values(array_map(
            static fn (array $group): SalesReportRow => new SalesReportRow(
                $group['branchId'],
                $branchNames[$group['branchId']] ?? '',
                $group['productId'],
                $productNames[$group['productId']] ?? '',
                $group['orderCount'],
                round($group['totalPrice'], 2),
                round($group['totalVat'], 2)
            ),
            $groups
        ));
    }
}
