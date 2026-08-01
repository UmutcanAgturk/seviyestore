<?php

declare(strict_types=1);

namespace Seviye\Reports;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Commerce\Contracts\OrderLineItemQueryInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Support\Environment;
use Seviye\Depo\Contracts\SupplierLookupInterface;
use Seviye\Depo\Contracts\WarehouseReportQueryInterface;
use Seviye\Reports\Http\OverviewRestController;
use Seviye\Reports\Http\ReportsRestController;
use Seviye\Reports\Http\WarehouseReportsRestController;
use Seviye\Reports\Rbac\ReportCapability;
use Seviye\Reports\Support\CsvExporter;
use Seviye\Reports\Support\SalesReportBuilder;
use Seviye\Reports\Support\WarehouseReportBuilder;
use Seviye\Reports\Support\XlsxExporter;
use Seviye\Students\Contracts\StudentLookupInterface;

/**
 * Reports owns no scp_* table and no migration - it is a pure read layer
 * over other modules' published Contracts (currently
 * Seviye\Commerce\Contracts\OrderLineItemQueryInterface,
 * Seviye\Branches\Contracts\BranchLookupInterface/BranchMembershipInterface,
 * and Seviye\Depo\Contracts\WarehouseReportQueryInterface/SupplierLookupInterface),
 * plus WooCommerce's own product/category APIs for names (a live lookup,
 * not a Contracts dependency - WooCommerce is not a Seviye module).
 */
final class ReportsModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'reports';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(SalesReportBuilder::class, static fn (): SalesReportBuilder => new SalesReportBuilder());
        $container->singleton(
            WarehouseReportBuilder::class,
            static fn (): WarehouseReportBuilder => new WarehouseReportBuilder()
        );
        $container->singleton(CsvExporter::class, static fn (): CsvExporter => new CsvExporter());
        $container->singleton(XlsxExporter::class, static fn (): XlsxExporter => new XlsxExporter());

        $rbac = $container->get(RbacManager::class);
        $rbac->grantCapability(Role::GENEL_MERKEZ, ReportCapability::VIEW_REPORTS->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, ReportCapability::VIEW_REPORTS->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, ReportCapability::VIEW_OWN_REPORTS->value);

        $container->get(RestApiRegistrar::class)->register(
            static fn (): ReportsRestController => new ReportsRestController(
                $container->get(OrderLineItemQueryInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(BranchMembershipInterface::class),
                $container->get(SalesReportBuilder::class),
                $container->get(CsvExporter::class),
                $container->get(XlsxExporter::class)
            )
        );

        // "Depo Raporları" (plan dokümanının faz 3'ü) - Depo'nun kendi
        // published Contract'ı üzerinden okur (ReportsRestController'ın
        // OrderLineItemQueryInterface'i kullanmasıyla aynı ilke), WC'ye
        // doğrudan hiç dokunmadığı için OverviewRestController'ın aksine
        // WC-active gate'inin DIŞINDA, ReportsRestController'la aynı yerde
        // kayıtlı.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): WarehouseReportsRestController => new WarehouseReportsRestController(
                $container->get(WarehouseReportQueryInterface::class),
                $container->get(SupplierLookupInterface::class),
                $container->get(WarehouseReportBuilder::class),
                $container->get(CsvExporter::class),
                $container->get(XlsxExporter::class)
            )
        );

        if (!Environment::isWooCommerceActive()) {
            return;
        }

        // "Genel Merkez için özet/anasayfa panosu" - unlike ReportsRestController
        // above (which reads the derived OrderLineItemQueryInterface table),
        // its route handler calls wc_get_orders() directly, so it is only
        // registered once WC is confirmed active - same gating Commerce's
        // own WC-touching controllers use.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): OverviewRestController => new OverviewRestController(
                $container->get(StudentLookupInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(BranchMembershipInterface::class)
            )
        );
    }
}
