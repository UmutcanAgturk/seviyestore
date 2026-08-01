<?php

declare(strict_types=1);

namespace Seviye\Reports\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Depo\Contracts\PurchaseOrderReportFilter;
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
 * seviye/v1/reports/warehouse - tedarikçi bazlı satın alma toplamı ve
 * zamanında teslim oranı ("Depo Raporları", plan dokümanının faz 3'ü).
 * Yalnızca scp_view_reports (Genel Merkez/Bölge Müdürü) - Şube Müdürü'nün
 * scp_view_own_reports'u burada geçerli değil, çünkü Depo modülünün
 * kendisi şube kavramından tamamen bağımsız ("tek bir depo vardır", bkz.
 * Seviye\Depo\DepoModule'in docblock'u) - raporlanacak hiçbir şube boyutu
 * yok. format=csv|xlsx aynı ReportsRestController::download() örüntüsü.
 */
final class WarehouseReportsRestController extends AbstractRestController
{
    public function __construct(
        private readonly WarehouseReportQueryInterface $purchaseOrders,
        private readonly SupplierLookupInterface $suppliers,
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
            'permission_callback' => $this->requireCapability(ReportCapability::VIEW_REPORTS->value),
        ]);
    }

    public function warehouse(WP_REST_Request $request): WP_REST_Response
    {
        $filter = new PurchaseOrderReportFilter(
            supplierId: $this->intParam($request, 'supplier_id'),
            fromDate: $this->stringParam($request, 'from'),
            toDate: $this->stringParam($request, 'to')
        );

        $records = $this->purchaseOrders->search($filter);
        $supplierIds = array_unique(array_map(static fn ($record): int => $record->supplierId, $records));
        $supplierNames = [];

        foreach ($supplierIds as $supplierId) {
            $supplierNames[$supplierId] = $this->suppliers->find($supplierId)?->name ?? '';
        }

        $rows = $this->builder->build($records, $supplierNames);

        return $this->respond($rows, (string) ($request->get_param('format') ?? 'json'));
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
