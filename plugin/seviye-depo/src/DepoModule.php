<?php

declare(strict_types=1);

namespace Seviye\Depo;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Support\Environment;
use Seviye\Depo\Contracts\PurchaseSuggestionSummaryInterface;
use Seviye\Depo\Contracts\SupplierLookupInterface;
use Seviye\Depo\Contracts\WarehouseReportQueryInterface;
use Seviye\Depo\Database\Migrations\CreatePurchaseOrderItemsTable;
use Seviye\Depo\Database\Migrations\CreatePurchaseOrdersTable;
use Seviye\Depo\Database\Migrations\CreatePurchaseSuggestionsTable;
use Seviye\Depo\Database\Migrations\CreateStockCountItemsTable;
use Seviye\Depo\Database\Migrations\CreateStockCountsTable;
use Seviye\Depo\Database\Migrations\CreateStockMovementsTable;
use Seviye\Depo\Database\Migrations\CreateSuppliersTable;
use Seviye\Depo\Http\PurchaseOrdersRestController;
use Seviye\Depo\Http\PurchaseSuggestionsRestController;
use Seviye\Depo\Http\StockCountsRestController;
use Seviye\Depo\Http\StockMovementsRestController;
use Seviye\Depo\Http\SuppliersRestController;
use Seviye\Depo\Rbac\WarehouseCapability;
use Seviye\Depo\Repository\PurchaseOrderRepositoryInterface;
use Seviye\Depo\Repository\PurchaseSuggestionRepositoryInterface;
use Seviye\Depo\Repository\StockCountRepositoryInterface;
use Seviye\Depo\Repository\StockMovementRepositoryInterface;
use Seviye\Depo\Repository\SupplierRepositoryInterface;
use Seviye\Depo\Repository\WpdbPurchaseOrderRepository;
use Seviye\Depo\Repository\WpdbPurchaseSuggestionRepository;
use Seviye\Depo\Repository\WpdbPurchaseSuggestionSummary;
use Seviye\Depo\Repository\WpdbStockCountRepository;
use Seviye\Depo\Repository\WpdbStockMovementRepository;
use Seviye\Depo\Repository\WpdbSupplierLookup;
use Seviye\Depo\Repository\WpdbSupplierRepository;
use Seviye\Depo\Repository\WpdbWarehouseReportQuery;
use Seviye\Depo\Support\LowStockPurchaseSuggestionListener;
use Seviye\Depo\Support\PurchaseOrderStatusCalculator;

/**
 * Faz 1-3'te "Tek bir depo vardır" (Branches'a hiç bağımlı değil) olan bu
 * modül, Faz 4'te "Genel Merkez'in kendi deposu devam eder, şube kendi
 * ürününü eklemişse o ürün şubenin kendi deposundan takip edilir" isteği
 * ile Branches'a bağımlı hale geldi (bkz. seviye-depo.php'nin Requires
 * Plugins başlığı, composer.json). Bir ürünün hangi depoya ait olduğu
 * hâlâ Commerce'in ProductOwnership'inden (scp_commerce_product_owner_branch_id
 * filter köprüsü - Depo, Commerce'e SERT bir composer bağımlılığı
 * eklemiyor, Pricing'in zaten kullandığı aynı gevşek köprüyü kullanıyor)
 * türetiliyor; Depo'nun kendi tablolarına (purchase_orders/stock_counts/
 * purchase_suggestions/stock_movements) bu değer YAZMA anında donduruluyor
 * - bkz. her migration'ın kendi branch_id sütun docblock'u. Ürün/stok
 * kaydı hâlâ WooCommerce'in kendisinde kalır (bkz. docs/ARCHITECTURE.md,
 * "Kural"); bu modül yalnızca tedarikçi, satın alma siparişi ve stok
 * hareketi defterini kendi tablolarında tutar.
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

        $container->singleton(
            StockCountRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbStockCountRepository => new WpdbStockCountRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            PurchaseSuggestionRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbPurchaseSuggestionRepository => new WpdbPurchaseSuggestionRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        // Published Contract (bkz. Contracts\WarehouseReportQueryInterface'in
        // docblock'u) - Reports'un "Depo Raporları" bölümü bu arayüz
        // üzerinden okur, Domain\PurchaseOrder'a asla doğrudan bağımlı
        // olmaz. Commerce'in OrderLineItemQueryInterface::class binding'iyle
        // aynı ilke.
        $container->singleton(
            WarehouseReportQueryInterface::class,
            static fn (ServiceContainer $c): WpdbWarehouseReportQuery => new WpdbWarehouseReportQuery(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            SupplierLookupInterface::class,
            static fn (ServiceContainer $c): WpdbSupplierLookup => new WpdbSupplierLookup(
                $c->get(ConnectionInterface::class)
            )
        );

        // Published Contract - Notifications'ın haftalık özet e-postası
        // bu arayüz üzerinden okur (bkz. Finance'ın HakedisTotalsInterface'i
        // ile aynı ilke).
        $container->singleton(
            PurchaseSuggestionSummaryInterface::class,
            static fn (ServiceContainer $c): WpdbPurchaseSuggestionSummary => new WpdbPurchaseSuggestionSummary(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateSuppliersTable());
        $container->get(MigrationRunner::class)->register(new CreatePurchaseOrdersTable());
        $container->get(MigrationRunner::class)->register(new CreatePurchaseOrderItemsTable());
        $container->get(MigrationRunner::class)->register(new CreateStockMovementsTable());
        $container->get(MigrationRunner::class)->register(new CreateStockCountsTable());
        $container->get(MigrationRunner::class)->register(new CreateStockCountItemsTable());
        $container->get(MigrationRunner::class)->register(new CreatePurchaseSuggestionsTable());

        $rbac = $container->get(RbacManager::class);

        foreach ([Role::GENEL_MERKEZ, Role::BOLGE_MUDURU, Role::DEPO] as $role) {
            $rbac->grantCapability($role, WarehouseCapability::MANAGE_SUPPLIERS->value);
            $rbac->grantCapability($role, WarehouseCapability::MANAGE_PURCHASE_ORDERS->value);
            $rbac->grantCapability($role, WarehouseCapability::RECEIVE_STOCK->value);
            $rbac->grantCapability($role, WarehouseCapability::VIEW_STOCK_MOVEMENTS->value);
            $rbac->grantCapability($role, WarehouseCapability::MANAGE_STOCK_COUNTS->value);
            $rbac->grantCapability($role, WarehouseCapability::MANAGE_PURCHASE_SUGGESTIONS->value);
        }

        // Faz 4: "şube kendi ürününü eklemiş ise şubenin kendi deposundan
        // görünecek" - Şube Müdürü yalnızca KENDİ şubesinin deposunu
        // yönetir, platform-wide capability'leri asla almaz (Genel Merkez
        // deposu veya başka bir şubenin deposu ona hiç görünmez - bkz. her
        // controller'ın resolveWarehouseBranchScope() benzeri metodu).
        // Tedarikçi listesi bilinçli olarak dışarıda - bkz.
        // WarehouseCapability'nin kendi docblock'u.
        $rbac->grantCapability(Role::SUBE_MUDURU, WarehouseCapability::MANAGE_OWN_BRANCH_PURCHASE_ORDERS->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, WarehouseCapability::VIEW_OWN_BRANCH_STOCK_MOVEMENTS->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, WarehouseCapability::MANAGE_OWN_BRANCH_STOCK_COUNTS->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, WarehouseCapability::MANAGE_OWN_BRANCH_PURCHASE_SUGGESTIONS->value);

        // "Tedarikçi portalı" - Security (AuthRestController) ve tema
        // (access-gate.php/zones.php) bu filtre üzerinden "bu kullanıcı bir
        // tedarikçiye mi bağlı" sorusunu sorar, Depo'ya sert bir composer
        // bağımlılığı eklemeden. Role enum'a yeni bir rol eklemek yerine
        // seçilen yaklaşım - bkz. SupplierRepositoryInterface::findByUserId().
        add_filter(
            'scp_depo_supplier_id_for_user',
            static function (?int $default, int $userId) use ($container): ?int {
                $repository = $container->get(SupplierRepositoryInterface::class);

                return $repository->findByUserId($userId)?->id ?? $default;
            },
            10,
            2
        );

        // Deferred to `init`: LowStockPurchaseSuggestionListener reacts to
        // commerce.product_low_stock, dispatched by Seviye Commerce - which
        // may not have booted yet within this same ModuleRegistry::bootAll()
        // pass (boot order follows plugin registration order, not
        // dependency order). Same reasoning as NotificationsModule's
        // identically-deferred LowStockNotificationListener registration.
        add_action('init', static function () use ($container): void {
            $lowStockSuggestionListener = new LowStockPurchaseSuggestionListener(
                $container->get(PurchaseSuggestionRepositoryInterface::class)
            );
            $container->get(EventBusInterface::class)->listen(
                'commerce.product_low_stock',
                [$lowStockSuggestionListener, 'onLowStock']
            );
        });

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
                $container->get(StockMovementRepositoryInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(BranchMembershipInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): StockMovementsRestController => new StockMovementsRestController(
                $container->get(StockMovementRepositoryInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(BranchMembershipInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): StockCountsRestController => new StockCountsRestController(
                $container->get(StockCountRepositoryInterface::class),
                $container->get(StockMovementRepositoryInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(BranchMembershipInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): PurchaseSuggestionsRestController => new PurchaseSuggestionsRestController(
                $container->get(PurchaseSuggestionRepositoryInterface::class),
                $container->get(PurchaseOrderRepositoryInterface::class),
                $container->get(SupplierRepositoryInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(BranchMembershipInterface::class)
            )
        );
    }
}
