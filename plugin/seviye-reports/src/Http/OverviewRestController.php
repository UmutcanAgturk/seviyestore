<?php

declare(strict_types=1);

namespace Seviye\Reports\Http;

use DateTimeImmutable;
use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Reports\Rbac\ReportCapability;
use Seviye\Reports\Support\DailyTrendBuilder;
use Seviye\Students\Contracts\StudentLookupInterface;
use WC_Order;
use WC_Order_Item_Product;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/reports/overview - "Genel Merkez için özet/anasayfa panosu":
 * bugün/son 7 gün/son 30 günün sipariş sayısı + cirosu, en çok satan
 * ürünler, ve (yalnızca HQ) şube bazlı kırılım. Scoping mirrors
 * ReportsRestController exactly (VIEW_REPORTS: her şube, VIEW_OWN_REPORTS:
 * yalnızca kendi şube).
 *
 * Reads wc_get_orders() directly (status=completed, son 30 gün) rather
 * than the OrderLineItemQueryInterface/scp_order_line_items path
 * ReportsRestController::sales() uses - same reasoning as
 * Seviye\Commerce\Http\AdminOrdersRestController (bkz. docs/ARCHITECTURE.md,
 * bölüm 36): a KPI dashboard drawing from a table that can silently miss a
 * row would show HQ numbers they can't actually trust. Each item's branch
 * is resolved here via StudentLookupInterface instead - existing sales
 * report/hakediş still read the derived table (out of scope for this
 * dashboard to fix).
 *
 * "completed" - the same "actually paid, not still refundable" definition
 * ReportsRestController::sales() already uses - a dashboard showing revenue
 * that might still be returned would be misleading, not merely incomplete.
 */
final class OverviewRestController extends AbstractRestController
{
    private const REPORT_STATUS = 'completed';
    private const STUDENT_META_KEY = '_scp_student_id';
    private const WINDOW_DAYS = 30;
    private const TOP_PRODUCTS_LIMIT = 5;

    public function __construct(
        private readonly StudentLookupInterface $students,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly DailyTrendBuilder $dailyTrendBuilder
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/reports/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => [$this, 'canViewReports'],
            'args' => [
                'from' => ['type' => 'string', 'required' => false],
                'to' => ['type' => 'string', 'required' => false],
                'compare_from' => ['type' => 'string', 'required' => false],
                'compare_to' => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    public function canViewReports(): bool
    {
        if (current_user_can(ReportCapability::VIEW_REPORTS->value)) {
            return true;
        }

        if (!current_user_can(ReportCapability::VIEW_OWN_REPORTS->value)) {
            return false;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id()) !== null;
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $isHq = current_user_can(ReportCapability::VIEW_REPORTS->value);
        $ownBranchId = $isHq ? null : $this->branchMemberships->branchIdForUser(get_current_user_id());

        $customFrom = $this->parseDate($request->get_param('from'));
        $customTo = $this->parseDate($request->get_param('to'));
        $compareFrom = $this->parseDate($request->get_param('compare_from'));
        $compareTo = $this->parseDate($request->get_param('compare_to'));

        $defaultSince = (new DateTimeImmutable('-' . self::WINDOW_DAYS . ' days'))->format('Y-m-d');
        $earliestNeeded = $defaultSince;

        foreach ([$customFrom, $compareFrom] as $candidate) {
            if ($candidate !== null && $candidate < $earliestNeeded) {
                $earliestNeeded = $candidate;
            }
        }

        $records = $this->records($ownBranchId, $earliestNeeded);

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $weekStart = (new DateTimeImmutable('-6 days'))->format('Y-m-d');
        $monthStart = (new DateTimeImmutable('-' . (self::WINDOW_DAYS - 1) . ' days'))->format('Y-m-d');

        // A custom range reaching further back than the standard 30-day
        // window widens the wc_get_orders() query above so periodSummary()
        // can see it - but every OTHER widget on this dashboard still
        // advertises itself as "Son 30 Gün" and must stay bounded to
        // exactly that, not silently grow because a comparison range asked
        // for older data in the SAME request.
        $windowRecords = array_values(array_filter(
            $records,
            static fn (array $record): bool => $record['date'] >= $defaultSince
        ));

        $response = [
            'today' => $this->periodSummary($windowRecords, $today, $today),
            'week' => $this->periodSummary($windowRecords, $weekStart, $today),
            'month' => $this->periodSummary($windowRecords, $monthStart, $today),
            'top_products' => $this->topProducts($windowRecords),
            'branch_breakdown' => $isHq ? $this->branchBreakdown($windowRecords) : [],
            'daily_trend' => $this->dailyTrendBuilder->build($windowRecords, $monthStart, $today),
            'custom_range' => null,
            'custom_range_comparison' => null,
        ];

        if ($customFrom !== null && $customTo !== null) {
            $primarySummary = $this->periodSummary($records, $customFrom, $customTo);
            $response['custom_range'] = array_merge(
                ['from' => $customFrom, 'to' => $customTo],
                $primarySummary
            );

            if ($compareFrom !== null && $compareTo !== null) {
                $compareSummary = $this->periodSummary($records, $compareFrom, $compareTo);
                $response['custom_range_comparison'] = array_merge(
                    ['from' => $compareFrom, 'to' => $compareTo],
                    $compareSummary,
                    ['percent_change' => $this->percentChange($compareSummary['total'], $primarySummary['total'])]
                );
            }
        }

        return new WP_REST_Response($response);
    }

    private function parseDate(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(?int $ownBranchId, string $since): array
    {
        $orders = wc_get_orders([
            'status' => self::REPORT_STATUS,
            'limit' => -1,
            'date_created' => '>=' . $since,
        ]);

        $records = [];

        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            $createdAt = $order->get_date_created();
            $date = $createdAt !== null ? $createdAt->date('Y-m-d') : null;

            if ($date === null) {
                continue;
            }

            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }

                $studentId = (int) $item->get_meta(self::STUDENT_META_KEY);

                if ($studentId <= 0) {
                    continue;
                }

                $student = $this->students->find($studentId);

                if ($student === null || ($ownBranchId !== null && $student->branchId !== $ownBranchId)) {
                    continue;
                }

                $records[] = [
                    'order_id' => $order->get_id(),
                    'date' => $date,
                    'branch_id' => $student->branchId,
                    'product_id' => $item->get_product_id(),
                    'product_name' => $item->get_name(),
                    'quantity' => max(1, $item->get_quantity()),
                    'line_total' => (float) $item->get_total(),
                ];
            }
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return array{order_count: int, total: float}
     */
    private function periodSummary(array $records, ?string $from, ?string $to): array
    {
        $orderIds = [];
        $total = 0.0;

        foreach ($records as $record) {
            if ($from !== null && $record['date'] < $from) {
                continue;
            }

            if ($to !== null && $record['date'] > $to) {
                continue;
            }

            $orderIds[$record['order_id']] = true;
            $total += $record['line_total'];
        }

        return ['order_count' => count($orderIds), 'total' => round($total, 2)];
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<array<string, mixed>>
     */
    private function topProducts(array $records): array
    {
        $byProduct = [];

        foreach ($records as $record) {
            $productId = $record['product_id'];

            if (!isset($byProduct[$productId])) {
                $byProduct[$productId] = [
                    'product_id' => $productId,
                    'name' => $record['product_name'],
                    'quantity' => 0,
                    'revenue' => 0.0,
                ];
            }

            $byProduct[$productId]['quantity'] += $record['quantity'];
            $byProduct[$productId]['revenue'] += $record['line_total'];
        }

        usort($byProduct, static fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);

        return array_map(
            static fn (array $row): array => [
                'product_id' => $row['product_id'],
                'name' => $row['name'],
                'quantity' => $row['quantity'],
                'revenue' => round($row['revenue'], 2),
            ],
            array_slice($byProduct, 0, self::TOP_PRODUCTS_LIMIT)
        );
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<array<string, mixed>>
     */
    private function branchBreakdown(array $records): array
    {
        $byBranch = [];

        foreach ($records as $record) {
            $branchId = $record['branch_id'];

            if (!isset($byBranch[$branchId])) {
                $byBranch[$branchId] = ['order_ids' => [], 'total' => 0.0];
            }

            $byBranch[$branchId]['order_ids'][$record['order_id']] = true;
            $byBranch[$branchId]['total'] += $record['line_total'];
        }

        $rows = [];

        foreach ($byBranch as $branchId => $data) {
            $rows[] = [
                'branch_id' => $branchId,
                'branch_name' => $this->branches->find($branchId)?->name ?? '',
                'order_count' => count($data['order_ids']),
                'total' => round($data['total'], 2),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $rows;
    }
}
