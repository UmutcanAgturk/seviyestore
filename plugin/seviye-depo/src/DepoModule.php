<?php

declare(strict_types=1);

namespace Seviye\Depo;

use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Support\Environment;
use Seviye\Depo\Database\Migrations\CreatePurchaseOrderItemsTable;
use Seviye\Depo\Database\Migrations\CreatePurchaseOrdersTable;
use Seviye\Depo\Database\Migrations\CreateStockMovementsTable;
use Seviye\Depo\Database\Migrations\CreateSuppliersTable;
use Seviye\Depo\Http\PurchaseOrdersRestController;
use Seviye\Depo\Http\StockMovementsRestController;
use Seviye\Depo\Http\SuppliersRestController;
use Seviye\Depo\Rbac\WarehouseCapability;
use Seviye\Depo\Repository\PurchaseOrderRepositoryInterface;
use Seviye\Depo\Repository\StockMovementRepositoryInterface;
use Seviye\Depo\Repository\SupplierRepositoryInterface;
use Seviye\Depo\Repository\WpdbPurchaseOrderRepository;
use Seviye\Depo\Repository\WpdbStockMovementRepository;
use Seviye\Depo\Repository\WpdbSupplierRepository;
use Seviye\Depo\Support\PurchaseOrderStatusCalculator;

/**
 * "Tek bir depo vardır" - bu modül şube kavramından tamamen bağımsız
 * (Branches/Students'ın Contracts'ına bağımlı değil, tek bağımlılığı
 * Core ve WooCommerce - bkz. seviye-depo.php'nin Requires Plugins başlığı).
 * Ürün/stok kaydı WooCommerce'in kendisinde kalır (bkz.
 * docs/ARCHITECTURE.md, "Kural"); bu modül yalnızca tedarikçi, satın alma
 * siparişi ve stok hareketi defterini kendi tablolarında tutar.
 */
final class DepoModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'depo';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            SupplierRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbSupplierRepository => new WpdbSupplierRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            StockMovementRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbStockMovementRepository => new WpdbStockMovementRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            PurchaseOrderRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbPurchaseOrderRepository => new WpdbPurchaseOrderRepository(
                $c->get(ConnectionInterface::class),
                new PurchaseOrderStatusCalculator()
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateSuppliersTable());
        $container->get(MigrationRunner::class)->register(new CreatePurchaseOrdersTable());
        $container->get(MigrationRunner::class)->register(new CreatePurchaseOrderItemsTable());
        $container->get(MigrationRunner::class)->register(new CreateStockMovementsTable());

        $rbac = $container->get(RbacManager::class);

        foreach ([Role::GENEL_MERKEZ, Role::BOLGE_MUDURU, Role::DEPO] as $role) {
            $rbac->grantCapability($role, WarehouseCapability::MANAGE_SUPPLIERS->value);
            $rbac->grantCapability($role, WarehouseCapability::MANAGE_PURCHASE_ORDERS->value);
            $rbac->grantCapability($role, WarehouseCapability::RECEIVE_STOCK->value);
            $rbac->grantCapability($role, WarehouseCapability::VIEW_STOCK_MOVEMENTS->value);
        }

        if (!Environment::isWooCommerceActive()) {
            return;
        }

        // WC-active gating mirrors CommerceModule::boot(): PurchaseOrdersRestController::receive()
        // calls wc_update_product_stock() directly.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): SuppliersRestController => new SuppliersRestController(
                $container->get(SupplierRepositoryInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): PurchaseOrdersRestController => new PurchaseOrdersRestController(
                $container->get(PurchaseOrderRepositoryInterface::class),
                $container->get(SupplierRepositoryInterface::class),
                $container->get(StockMovementRepositoryInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): StockMovementsRestController => new StockMovementsRestController(
                $container->get(StockMovementRepositoryInterface::class)
            )
        );
    }
}
