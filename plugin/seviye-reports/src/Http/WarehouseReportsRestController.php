<?php

declare(strict_types=1);

namespace Seviye\Reports\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Depo\Contracts\PurchaseOrderReportFilter;
use Seviye\Depo\Contracts\PurchaseOrderReportRecord;
use Seviye\Depo\Contracts\SupplierLookupInterface;
use Seviye\Depo\Contracts\WarehouseReportQueryInterface;
use Seviye\Reports\Domain\WarehouseReportRow;
use Seviye\Reports\Rbac\ReportCapability;
use Seviye\Reports\Support\CsvExporter;
use Seviye\Reports\Support\WarehouseReportBuilder;
use Seviye\Reports\Support\XlsxExporter;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/reports/warehouse - tedarikçi/depo bazlı satın alma toplamı ve
 * zamanında teslim oranı ("Depo Raporları", plan dokümanının faz 3'ü).
 *
 * Faz 4: Depo artık şube kavramından bağımsız değil ("Genel Merkez'in
 * kendi deposu devam eder, şube kendi ürününü eklemişse şubenin kendi
 * deposundan görünür/takip edilir") - bu yüzden scoping artık
 * ReportsRestController::canViewReports()/effectiveBranchId() ile AYNI
 * desen: HQ (VIEW_REPORTS) herhangi bir depoyu (ya da branch_id
 * verilmezse hepsini) raporlayabilir; Şube Müdürü (VIEW_OWN_REPORTS) her
 * zaman yalnızca kendi şubesinin deposuna kilitlenir. `branch_id` isteği
 * Depo'nun kendi REST controller'larıyla aynı üç durumlu kodlamayı
 * kullanır: boş/yok = filtre yok, 'hq' = yalnızca Genel Merkez deposu, bir
 * sayı = yalnızca o şubenin deposu (bkz. effectiveBranchId()).
 *
 * format=csv|xlsx aynı ReportsRestController::download() örüntüsü.
 */
final class WarehouseReportsRestController extends AbstractRestController
{
    public function __construct(
        private readonly WarehouseReportQueryInterface $purchaseOrders,
        private readonly SupplierLookupInterface $suppliers,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly WarehouseReportBuilder $builder,
        private readonly CsvExporter $csvExporter,
        private readonly XlsxExporter $xlsxExporter
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/reports/warehouse', [
            'methods' => 'GET',
            'callback' => [$this, 'warehouse'],
            'permission_callback' => [$this, 'canViewReports'],
        ]);
    }

    /**
     * Mirrors ReportsRestController::canViewReports() - see that method's
     * own docblock for the "own-branch role's requested branch_id must
     * match their own branch, or be omitted" reasoning. The 'hq' sentinel
     * is always denied for an own-branch role - Şube Müdürü never sees
     * Genel Merkez'in deposu, not even read-only.
     */
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

        $requested = $request->get_param('branch_id');

        return $requested === null || $requested === '' || (string) $ownBranchId === (string) $requested;
    }

    public function warehouse(WP_REST_Request $request): WP_REST_Response
    {
        $filter = new PurchaseOrderReportFilter(
            supplierId: $this->intParam($request, 'supplier_id'),
            fromDate: $this->stringParam($request, 'from'),
            toDate: $this->stringParam($request, 'to'),
            branchId: $this->effectiveBranchId($request)
        );

        $records = $this->purchaseOrders->search($filter);
        $supplierIds = array_unique(array_map(static fn ($record): int => $record->supplierId, $records));
        $supplierNames = [];

        foreach ($supplierIds as $supplierId) {
            $supplierNames[$supplierId] = $this->suppliers->find($supplierId)?->name ?? '';
        }

        $rows = $this->builder->build($records, $supplierNames, $this->branchNamesFor($records));

        return $this->respond($rows, (string) ($request->get_param('format') ?? 'json'));
    }

    /**
     * Same "server resolves the scope, never trusts the client's" rule as
     * ReportsRestController::effectiveBranchId() - except the three-state
     * false/null/int sentinel, since Genel Merkez's own depo is itself a
     * distinct, selectable scope here (bkz. PurchaseOrderReportFilter'ın
     * kendi docblock'u).
     */
    private function effectiveBranchId(WP_REST_Request $request): int|false|null
    {
        if (current_user_can(ReportCapability::VIEW_REPORTS->value)) {
            $raw = $request->get_param('branch_id');

            if ($raw === null || $raw === '') {
                return false;
            }

            return $raw === 'hq' ? null : (int) $raw;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    /**
     * @param list<PurchaseOrderReportRecord> $records
     * @return array<int, string>
     */
    private function branchNamesFor(array $records): array
    {
        $names = [];
        $branchIds = array_unique(array_filter(
            array_map(static fn (PurchaseOrderReportRecord $r): ?int => $r->branchId, $records)
        ));

        foreach ($branchIds as $branchId) {
            $names[$branchId] = $this->branches->find($branchId)?->name ?? '';
        }

        return $names;
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
     * @param list<WarehouseReportRow> $rows
     */
    private function respond(array $rows, string $format): WP_REST_Response
    {
        if ($format === 'csv') {
            $this->download($this->csvExporter->exportWarehouse($rows), 'text/csv; charset=UTF-8', 'depo-raporu.csv');
        }

        if ($format === 'xlsx') {
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $this->download($this->xlsxExporter->exportWarehouse($rows), $mime, 'depo-raporu.xlsx');
        }

        return new WP_REST_Response(array_map(
            static fn (WarehouseReportRow $row): array => [
                'supplier_id' => $row->supplierId,
                'supplier_name' => $row->supplierName,
                'branch_id' => $row->branchId,
                'branch_name' => $row->branchName,
                'order_count' => $row->orderCount,
                'total_cost' => $row->totalCost,
                'completed_order_count' => $row->completedOrderCount,
                'on_time_rate' => $row->onTimeRate,
            ],
            $rows
        ));
    }

    /**
     * Bypasses the REST JSON envelope entirely - see
     * ReportsRestController::download()'s identical docblock.
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
