<?php

declare(strict_types=1);

namespace Seviye\Reports\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Commerce\Contracts\OrderLineItemFilter;
use Seviye\Commerce\Contracts\OrderLineItemQueryInterface;
use Seviye\Commerce\Contracts\OrderLineItemRecord;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Reports\Domain\SalesReportRow;
use Seviye\Reports\Rbac\ReportCapability;
use Seviye\Reports\Support\CsvExporter;
use Seviye\Reports\Support\SalesReportBuilder;
use Seviye\Reports\Support\XlsxExporter;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/reports/sales - şube/ürün/kategori/dönem bazlı satış raporu.
 * Only ever reports on `completed` order line items (the same "actually
 * paid, not still refundable" definition Seviye Finance's hakediş trigger
 * uses) - a report showing money that might still be returned would be
 * misleading, not merely incomplete.
 *
 * Scoping mirrors Seviye\Finance\Http\HakedisRestController exactly: HQ
 * (VIEW_REPORTS) may report on any branch (or all branches, if no
 * branch_id given); Şube Müdürü (VIEW_OWN_REPORTS) is always scoped to
 * their own branch, regardless of what the request asks for.
 *
 * format=csv|xlsx bypasses the normal WP_REST_Response JSON envelope
 * entirely (header()+echo+exit before returning) - the standard way a WP
 * REST endpoint serves a raw file download, since WP_REST_Server would
 * otherwise json_encode() whatever is returned. Authenticated the same way
 * as every other panel endpoint (cookie+nonce), but reachable via a plain
 * browser navigation/download by accepting the nonce as a `_wpnonce` query
 * parameter (a WordPress-supported alternative to the X-WP-Nonce header -
 * rest_cookie_check_errors() checks both), since a `<a href>` download
 * click cannot attach custom headers the way fetch() can.
 */
final class ReportsRestController extends AbstractRestController
{
    private const REPORT_STATUS = 'completed';

    public function __construct(
        private readonly OrderLineItemQueryInterface $orderLineItems,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly SalesReportBuilder $builder,
        private readonly CsvExporter $csvExporter,
        private readonly XlsxExporter $xlsxExporter
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/reports/sales', [
            'methods' => 'GET',
            'callback' => [$this, 'sales'],
            'permission_callback' => [$this, 'canViewReports'],
        ]);
    }

    public function canViewReports(WP_REST_Request $request): bool
    {
        if (current_user_can(ReportCapability::VIEW_REPORTS->value)) {
            return true;
        }

        if (!current_user_can(ReportCapability::VIEW_OWN_REPORTS->value)) {
            return false;
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        if ($ownBranchId === null) {
            return false;
        }

        $requestedBranchId = $request->get_param('branch_id');

        return $requestedBranchId === null || (int) $requestedBranchId === $ownBranchId;
    }

    public function sales(WP_REST_Request $request): WP_REST_Response
    {
        $filter = new OrderLineItemFilter(
            branchId: $this->effectiveBranchId($request),
            productId: $this->intParam($request, 'product_id'),
            fromDate: $this->stringParam($request, 'from'),
            toDate: $this->stringParam($request, 'to'),
            status: self::REPORT_STATUS
        );

        $records = $this->orderLineItems->search($filter);

        $categoryId = $this->intParam($request, 'category_id');

        if ($categoryId !== null) {
            $records = $this->filterByCategory($records, $categoryId);
        }

        $rows = $this->builder->build($records, $this->branchNamesFor($records), $this->productNamesFor($records));

        return $this->respond($rows, (string) ($request->get_param('format') ?? 'json'));
    }

    /**
     * HQ may filter by a branch_id, or omit it for every branch; a
     * branch-scoped role's own branch always wins over whatever the
     * request asks for - the same "server resolves the scope, never
     * trusts the client's" rule Students/Pricing already apply to writes,
     * here applied to a read.
     */
    private function effectiveBranchId(WP_REST_Request $request): ?int
    {
        if (current_user_can(ReportCapability::VIEW_REPORTS->value)) {
            return $this->intParam($request, 'branch_id');
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    private function intParam(WP_REST_Request $request, string $name): ?int
    {
        $value = $request->get_param($name);

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function stringParam(WP_REST_Request $request, string $name): ?string
    {
        $value = $request->get_param($name);

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @param list<OrderLineItemRecord> $records
     * @return list<OrderLineItemRecord>
     */
    private function filterByCategory(array $records, int $categoryId): array
    {
        return array_values(array_filter(
            $records,
            static fn (OrderLineItemRecord $record): bool => function_exists('has_term')
                && has_term($categoryId, 'product_cat', $record->productId)
        ));
    }

    /**
     * @param list<OrderLineItemRecord> $records
     * @return array<int, string>
     */
    private function branchNamesFor(array $records): array
    {
        $names = [];
        $branchIds = array_unique(array_map(static fn (OrderLineItemRecord $r): int => $r->branchId, $records));

        foreach ($branchIds as $branchId) {
            $names[$branchId] = $this->branches->find($branchId)?->name ?? '';
        }

        return $names;
    }

    /**
     * @param list<OrderLineItemRecord> $records
     * @return array<int, string>
     */
    private function productNamesFor(array $records): array
    {
        $names = [];
        $productIds = array_unique(array_map(static fn (OrderLineItemRecord $r): int => $r->productId, $records));

        foreach ($productIds as $productId) {
            $product = function_exists('wc_get_product') ? wc_get_product($productId) : false;
            $names[$productId] = $product !== false && $product !== null
                ? $product->get_name()
                /* translators: %d: WooCommerce product ID */
                : sprintf(__('Ürün #%d', 'seviye-reports'), $productId);
        }

        return $names;
    }

    /**
     * @param list<SalesReportRow> $rows
     */
    private function respond(array $rows, string $format): WP_REST_Response
    {
        if ($format === 'csv') {
            $this->download($this->csvExporter->export($rows), 'text/csv; charset=UTF-8', 'satis-raporu.csv');
        }

        if ($format === 'xlsx') {
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $this->download($this->xlsxExporter->export($rows), $mime, 'satis-raporu.xlsx');
        }

        return new WP_REST_Response(array_map(
            static fn (SalesReportRow $row): array => [
                'branch_id' => $row->branchId,
                'branch_name' => $row->branchName,
                'product_id' => $row->productId,
                'product_name' => $row->productName,
                'order_count' => $row->orderCount,
                'total_price' => $row->totalPrice,
                'total_vat' => $row->totalVat,
            ],
            $rows
        ));
    }

    /**
     * Bypasses the REST JSON envelope entirely - see class docblock.
     */
    private function download(string $contents, string $contentType, string $filename): never
    {
        nocache_headers();
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($contents));
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw file bytes (CSV/XLSX export), not HTML output.
        echo $contents;
        exit;
    }
}
