<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Support\Environment;
use Seviye\SubeSiparis\Database\Migrations\CreateBranchOrderItemsTable;
use Seviye\SubeSiparis\Database\Migrations\CreateBranchOrderQuotasTable;
use Seviye\SubeSiparis\Database\Migrations\CreateBranchOrdersTable;
use Seviye\SubeSiparis\Http\BranchOrderQuotasRestController;
use Seviye\SubeSiparis\Http\BranchOrdersRestController;
use Seviye\SubeSiparis\Http\WooCommercePaymentBridge;
use Seviye\SubeSiparis\Rbac\BranchOrderCapability;
use Seviye\SubeSiparis\Repository\BranchOrderQuotaRepositoryInterface;
use Seviye\SubeSiparis\Repository\BranchOrderRepositoryInterface;
use Seviye\SubeSiparis\Repository\WpdbBranchOrderQuotaRepository;
use Seviye\SubeSiparis\Repository\WpdbBranchOrderRepository;
use Seviye\SubeSiparis\Support\BranchOrderSplitCalculator;

/**
 * "Şubeler için ayrı bir /sube paneli - her şube için ayrı ayrı ürün girişi
 * yapılabilsin, sayıları Genel Merkez her ürün için ayrı ayrı belirlesin,
 * Genel Merkez onayı, kota aşımı gerçek bir ödeme (kart) ile tamamlansın."
 *
 * Branches'a SERT bir composer bağımlılığı var (Requires Plugins,
 * composer.json) - Depo/Finance/Pricing/Commerce'in de yaptığı gibi: bir
 * şube siparişi gerçekten bir şubeye ait, bu gerçek bir domain ilişkisi,
 * gevşek bir filter köprüsü değil (bkz. Depo'nun ProductOwnership köprüsü
 * ile karşılaştırma - DepoModule::boot()'un kendi docblock'u). Commerce'e
 * ise HİÇBİR bağımlılığı yok (ne sert ne gevşek) - ödemenin aşan kısmı
 * doğrudan WooCommerce'in kendi wc_create_order() API'siyle açılıyor (bkz.
 * Http\WooCommercePaymentBridge), Commerce'in kendi sepet/checkout akışına
 * hiç girmiyor.
 */
final class SubeSiparisModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'sube-siparis';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            BranchOrderQuotaRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbBranchOrderQuotaRepository => new WpdbBranchOrderQuotaRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            BranchOrderRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbBranchOrderRepository => new WpdbBranchOrderRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            BranchOrderSplitCalculator::class,
            static fn (): BranchOrderSplitCalculator => new BranchOrderSplitCalculator()
        );

        $container->singleton(
            WooCommercePaymentBridge::class,
            static fn (ServiceContainer $c): WooCommercePaymentBridge => new WooCommercePaymentBridge(
                $c->get(BranchOrderRepositoryInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateBranchOrderQuotasTable());
        $container->get(MigrationRunner::class)->register(new CreateBranchOrdersTable());
        $container->get(MigrationRunner::class)->register(new CreateBranchOrderItemsTable());

        $rbac = $container->get(RbacManager::class);

        foreach ([Role::GENEL_MERKEZ, Role::BOLGE_MUDURU] as $role) {
            $rbac->grantCapability($role, BranchOrderCapability::MANAGE_BRANCH_ORDERS->value);
        }

        $rbac->grantCapability(Role::SUBE_MUDURU, BranchOrderCapability::MANAGE_OWN_BRANCH_ORDERS->value);

        if (!Environment::isWooCommerceActive()) {
            return;
        }

        // woocommerce_order_status_changed dinleyicisi WC-active gating'e
        // tabi (WooCommercePaymentBridge doğrudan WC_Order'a bağımlı) -
        // aynı Commerce/Depo'nun kendi WC-active guard'ı ile aynı ilke.
        $container->get(WooCommercePaymentBridge::class)->register();

        $container->get(RestApiRegistrar::class)->register(
            static fn (): BranchOrderQuotasRestController => new BranchOrderQuotasRestController(
                $container->get(BranchOrderQuotaRepositoryInterface::class),
                $container->get(BranchOrderRepositoryInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(BranchMembershipInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): BranchOrdersRestController => new BranchOrdersRestController(
                $container->get(BranchOrderRepositoryInterface::class),
                $container->get(BranchOrderQuotaRepositoryInterface::class),
                $container->get(BranchOrderSplitCalculator::class),
                $container->get(WooCommercePaymentBridge::class),
                $container->get(BranchLookupInterface::class),
                $container->get(BranchMembershipInterface::class)
            )
        );
    }
}
