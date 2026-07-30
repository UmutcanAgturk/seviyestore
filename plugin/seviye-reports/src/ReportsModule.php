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
use Seviye\Reports\Http\ReportsRestController;
use Seviye\Reports\Rbac\ReportCapability;
use Seviye\Reports\Support\CsvExporter;
use Seviye\Reports\Support\SalesReportBuilder;
use Seviye\Reports\Support\XlsxExporter;

/**
 * Reports owns no scp_* table and no migration - it is a pure read layer
 * over other modules' published Contracts (currently
 * Seviye\Commerce\Contracts\OrderLineItemQueryInterface +
 * Seviye\Branches\Contracts\BranchLookupInterface/BranchMembershipInterface),
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
    }
}
