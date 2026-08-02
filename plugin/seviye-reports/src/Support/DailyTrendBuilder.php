<?php

declare(strict_types=1);

namespace Seviye\Reports\Support;

use DateTimeImmutable;

/**
 * Zero-fills a date range then buckets already-fetched order records into
 * it - a day with zero orders must still appear as a `0` point, never be
 * silently absent, or a trend chart drawn from this would render a
 * misleading gap-free line across days that actually had no sales. Kept
 * free of any WordPress/WooCommerce call (DateTimeImmutable is plain PHP),
 * the same "Support classes stay pure, Http classes touch the platform"
 * split SalesReportBuilder documents - OverviewRestController does the
 * wc_get_orders() call and hands this class plain arrays.
 */
final class DailyTrendBuilder
{
    /**
     * @param list<array{order_id: int, date: string, line_total: float}> $records
     * @return list<array{date: string, order_count: int, total: float}>
     */
    public function build(array $records, string $fromDate, string $toDate): array
    {
        $buckets = [];
        $cursor = new DateTimeImmutable($fromDate);
        $end = new DateTimeImmutable($toDate);

        while ($cursor <= $end) {
            $buckets[$cursor->format('Y-m-d')] = ['orderIds' => [], 'total' => 0.0];
            $cursor = $cursor->modify('+1 day');
        }

        foreach ($records as $record) {
            if (!isset($buckets[$record['date']])) {
                continue;
            }

            $buckets[$record['date']]['orderIds'][$record['order_id']] = true;
            $buckets[$record['date']]['total'] += $record['line_total'];
        }

        $result = [];

        foreach ($buckets as $date => $bucket) {
            $result[] = [
                'date' => $date,
                'order_count' => count($bucket['orderIds']),
                'total' => round($bucket['total'], 2),
            ];
        }

        return $result;
    }
}
