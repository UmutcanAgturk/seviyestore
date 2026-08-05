<?php

declare(strict_types=1);

namespace Seviye\Commerce;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Commerce\Contracts\OrderLineItemQueryInterface;
use Seviye\Commerce\Database\Migrations\CreateOrderLineItemsTable;
use Seviye\Commerce\Database\Migrations\CreateProductBranchesTable;
use Seviye\Commerce\Http\AdminOrdersRestController;
use Seviye\Commerce\Http\CustomerAddressRestController;
use Seviye\Commerce\Database\Migrations\CreateStudentSpendingLimitsTable;
use Seviye\Commerce\Http\CouponsRestController;
use Seviye\Commerce\Http\LowStockNotificationHooks;
use Seviye\Commerce\Http\OrderPersistenceHooks;
use Seviye\Commerce\Http\OrdersRestController;
use Seviye\Commerce\Http\ProductOwnershipBridge;
use Seviye\Commerce\Http\ProductReviewGate;
use Seviye\Commerce\Http\ProductsRestController;
use Seviye\Commerce\Http\ProductVisibilityHooks;
use Seviye\Commerce\Http\SpendingLimitCartHooks;
use Seviye\Commerce\Http\SpendingLimitRestController;
use Seviye\Commerce\Http\StorefrontPriceDisplayHooks;
use Seviye\Commerce\Http\Support\OrderPresenter;
use Seviye\Commerce\Http\WooCommerceCartHooks;
use Seviye\Commerce\Rbac\CouponCapability;
use Seviye\Commerce\Rbac\OrderCapability;
use Seviye\Commerce\Rbac\ProductCapability;
use Seviye\Commerce\Repository\OrderLineItemRepositoryInterface;
use Seviye\Commerce\Repository\ProductBranchVisibilityRepositoryInterface;
use Seviye\Commerce\Repository\SpendingLimitRepositoryInterface;
use Seviye\Commerce\Repository\WpdbOrderLineItemQuery;
use Seviye\Commerce\Repository\WpdbOrderLineItemRepository;
use Seviye\Commerce\Repository\WpdbProductBranchVisibilityRepository;
use Seviye\Commerce\Repository\WpdbSpendingLimitRepository;
use Seviye\Commerce\Support\CartPricingService;
use Seviye\Commerce\Support\OrderFulfillment;
use Seviye\Commerce\Support\OrderPayloadBuilder;
use Seviye\Commerce\Support\ProductGradeLevels;
use Seviye\Commerce\Support\ProductOwnership;
use Seviye\Commerce\Support\SplitPaymentCalculator;
use Seviye\Commerce\Support\StudentSpendingCalculator;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Support\Environment;
use Seviye\Pricing\Contracts\PriceResolverInterface;
use Seviye\Students\Contracts\ParentBranchLookupInterface;
use Seviye\Students\Contracts\ParentClassLookupInterface;
use Seviye\Students\Contracts\StudentGuardianCheckInterface;
use Seviye\Students\Contracts\StudentLookupInterface;

/**
 * Fourth module (after Students, Pricing, and now also Branches directly -
 * previously only a transitive dependency) to depend on other modules'
 * Contracts. seviye/commerce keeps exactly one table of its own
 * (scp_order_line_items, a denormalized snapshot for later hakediş
 * calculation) - order/cart storage itself stays WooCommerce's. See
 * docs/ARCHITECTURE.md, "Kural".
 */
final class CommerceModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'commerce';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            CartPricingService::class,
            static fn (ServiceContainer $c): CartPricingService => new CartPricingService(
                $c->get(StudentGuardianCheckInterface::class),
                $c->get(PriceResolverInterface::class)
            )
        );

        $container->singleton(
            OrderLineItemRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbOrderLineItemRepository => new WpdbOrderLineItemRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            OrderLineItemQueryInterface::class,
            static fn (ServiceContainer $c): WpdbOrderLineItemQuery => new WpdbOrderLineItemQuery(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            ProductBranchVisibilityRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbProductBranchVisibilityRepository =>
                new WpdbProductBranchVisibilityRepository($c->get(ConnectionInterface::class))
        );

        $container->singleton(
            SpendingLimitRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbSpendingLimitRepository => new WpdbSpendingLimitRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            StudentSpendingCalculator::class,
            static fn (): StudentSpendingCalculator => new StudentSpendingCalculator()
        );

        $container->singleton(
            ProductOwnership::class,
            static fn (): ProductOwnership => new ProductOwnership()
        );

        $container->singleton(
            ProductGradeLevels::class,
            static fn (): ProductGradeLevels => new ProductGradeLevels()
        );

        $container->get(MigrationRunner::class)->register(new CreateOrderLineItemsTable());
        $container->get(MigrationRunner::class)->register(new CreateProductBranchesTable());
        $container->get(MigrationRunner::class)->register(new CreateStudentSpendingLimitsTable());

        $rbac = $container->get(RbacManager::class);
        $rbac->grantCapability(Role::GENEL_MERKEZ, ProductCapability::MANAGE_PRODUCTS->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, ProductCapability::MANAGE_PRODUCTS->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, ProductCapability::MANAGE_PRODUCTS->value);

        // Read-only: "Bütün ürün id'leri Genel Merkez, Sistem, şube
        // müdürleri, muhasebe, depodan görünür olsun" - Genel Merkez/Şube
        // Müdürü already see everything via MANAGE_PRODUCTS above (a
        // superset); these three roles get VIEW_PRODUCTS instead, since
        // they must never create/edit/delete/toggle a product.
        $rbac->grantCapability(Role::SISTEM, ProductCapability::VIEW_PRODUCTS->value);
        $rbac->grantCapability(Role::MUHASEBE, ProductCapability::VIEW_PRODUCTS->value);
        $rbac->grantCapability(Role::DEPO, ProductCapability::VIEW_PRODUCTS->value);

        // Product photo uploads go through WordPress' own /wp/v2/media REST
        // endpoint from the theme's "Ürünler" panel - simpler and more
        // robust than reinventing file upload handling, but it requires
        // the native `upload_files` capability, which none of these custom
        // roles carry by default (every custom WP role starts with zero
        // capabilities).
        $rbac->grantCapability(Role::GENEL_MERKEZ, 'upload_files');
        $rbac->grantCapability(Role::BOLGE_MUDURU, 'upload_files');
        $rbac->grantCapability(Role::SUBE_MUDURU, 'upload_files');

        // "Genel merkez hesabından tüm siparişleri, şube ise kendi
        // velilerin siparişlerini görecek bir menü" - see
        // Http\AdminOrdersRestController.
        $rbac->grantCapability(Role::GENEL_MERKEZ, OrderCapability::VIEW_ORDERS->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, OrderCapability::VIEW_ORDERS->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, OrderCapability::VIEW_OWN_BRANCH_ORDERS->value);

        // "İade/iptal akışı" - iptal (henüz ödenmemiş bir siparişi para
        // hareketi olmadan durdurma) VIEW_ORDERS ile aynı üç rolde; iade
        // (gerçek para iadesi) Finance'in HakedisCapability::RECORD_SETTLEMENT'ıyla
        // aynı ilkeyle yalnızca Genel Merkez/Bölge Müdürü/Muhasebe'de - bkz.
        // Rbac\OrderCapability'nin REFUND_ORDERS docblock'u.
        $rbac->grantCapability(Role::GENEL_MERKEZ, OrderCapability::CANCEL_ORDERS->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, OrderCapability::CANCEL_ORDERS->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, OrderCapability::CANCEL_OWN_BRANCH_ORDERS->value);
        $rbac->grantCapability(Role::GENEL_MERKEZ, OrderCapability::REFUND_ORDERS->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, OrderCapability::REFUND_ORDERS->value);
        $rbac->grantCapability(Role::MUHASEBE, OrderCapability::REFUND_ORDERS->value);

        // "Kargoya verildi/teslim edildi" - same three-role/branch-scoped
        // split as VIEW_ORDERS/CANCEL_ORDERS above (a logistics update, not
        // a money movement, so - unlike REFUND_ORDERS - Şube Müdürü gets a
        // tier here too).
        $rbac->grantCapability(Role::GENEL_MERKEZ, OrderCapability::UPDATE_ORDER_FULFILLMENT->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, OrderCapability::UPDATE_ORDER_FULFILLMENT->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, OrderCapability::UPDATE_OWN_BRANCH_ORDER_FULFILLMENT->value);

        // "Kupon/kampanya kodu sistemi" - platform/campaign-level, HQ-only
        // (no Şube Müdürü tier, unlike Products/Orders) - see CouponCapability.
        $rbac->grantCapability(Role::GENEL_MERKEZ, CouponCapability::MANAGE_COUPONS->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, CouponCapability::MANAGE_COUPONS->value);

        $container->singleton(
            OrderFulfillment::class,
            static fn (): OrderFulfillment => new OrderFulfillment()
        );

        $container->singleton(
            OrderPayloadBuilder::class,
            static fn (): OrderPayloadBuilder => new OrderPayloadBuilder()
        );

        $container->singleton(
            OrderPresenter::class,
            static fn (ServiceContainer $c): OrderPresenter => new OrderPresenter(
                $c->get(StudentLookupInterface::class),
                $c->get(OrderFulfillment::class)
            )
        );

        if (!Environment::isWooCommerceActive()) {
            return;
        }

        // ProductsRestController's route handlers call WooCommerce
        // functions (wc_get_product(s), WC_Product_Simple) directly, unlike
        // the DB/RBAC wiring above - only registered once WC is confirmed
        // active, matching the reasoning for deferring the two hook
        // adapters below.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): ProductsRestController => new ProductsRestController(
                $container->get(ProductBranchVisibilityRepositoryInterface::class),
                $container->get(BranchMembershipInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(ProductOwnership::class),
                $container->get(ProductGradeLevels::class)
            )
        );

        // Same WC-active gating as ProductsRestController above - its
        // route handler calls wc_get_orders()/WC_Order directly.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): OrdersRestController => new OrdersRestController(
                $container->get(OrderPresenter::class)
            )
        );

        // Admin/Şube Müdürü order listing - same WC-active gating, its
        // route handler calls wc_get_orders() directly.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): AdminOrdersRestController => new AdminOrdersRestController(
                $container->get(StudentLookupInterface::class),
                $container->get(BranchMembershipInterface::class),
                $container->get(OrderPresenter::class),
                $container->get(OrderFulfillment::class),
                $container->get(OrderPayloadBuilder::class),
                $container->get(EventBusInterface::class)
            )
        );

        // "Velinin profilinde Gönderim adresi ve fatura adresi bölümü de
        // olsun" - same WC-active gating, its route handlers construct
        // WC_Customer directly.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): CustomerAddressRestController => new CustomerAddressRestController()
        );

        // "Öğrenci/veli bazlı harcama limiti" - reuses Students' own
        // scp_manage_students capability (already granted to
        // Genel Merkez/Bölge Müdürü/Şube Müdürü by StudentsModule::boot()),
        // no new capability grant needed here. Same WC-active gating as the
        // controllers above - its spend calculation calls wc_get_orders()
        // directly.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): SpendingLimitRestController => new SpendingLimitRestController(
                $container->get(SpendingLimitRepositoryInterface::class),
                $container->get(StudentSpendingCalculator::class),
                $container->get(StudentLookupInterface::class),
                $container->get(BranchMembershipInterface::class)
            )
        );

        // Same WC-active gating as the controllers above - its route
        // handlers construct WC_Coupon directly.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): CouponsRestController => new CouponsRestController()
        );

        // Deferred to `init` (not resolved here in boot()): CartPricingService and
        // OrderPersistenceHooks depend on other modules' Contracts (Students,
        // Branches), and ModuleRegistry::bootAll() boots modules in plugin
        // *registration* order, not dependency order - that order tracks each
        // plugin's activation history on the site, which this module cannot rely
        // on. `init` always fires after every module's boot() has run, so by then
        // every Contract binding this needs is guaranteed to exist regardless of
        // which module booted first.
        add_action('init', static function () use ($container): void {
            $cartHooks = new WooCommerceCartHooks(
                $container->get(CartPricingService::class),
                $container->get(StudentLookupInterface::class)
            );
            $cartHooks->register();

            $spendingLimitCartHooks = new SpendingLimitCartHooks(
                $container->get(SpendingLimitRepositoryInterface::class),
                $container->get(StudentSpendingCalculator::class)
            );
            $spendingLimitCartHooks->register();

            $orderHooks = new OrderPersistenceHooks(
                $container->get(OrderLineItemRepositoryInterface::class),
                $container->get(StudentLookupInterface::class),
                $container->get(BranchLookupInterface::class),
                new SplitPaymentCalculator(),
                $container->get(EventBusInterface::class),
                $container->get(OrderPayloadBuilder::class)
            );
            $orderHooks->register();

            $visibilityHooks = new ProductVisibilityHooks(
                $container->get(ProductBranchVisibilityRepositoryInterface::class),
                $container->get(ParentBranchLookupInterface::class),
                $container->get(ParentClassLookupInterface::class),
                $container->get(ProductOwnership::class),
                $container->get(ProductGradeLevels::class)
            );
            $visibilityHooks->register();

            (new ProductOwnershipBridge($container->get(ProductOwnership::class)))->register();

            $priceDisplayHooks = new StorefrontPriceDisplayHooks(
                $container->get(PriceResolverInterface::class),
                $container->get(ParentBranchLookupInterface::class)
            );
            $priceDisplayHooks->register();

            (new LowStockNotificationHooks($container->get(EventBusInterface::class)))->register();

            (new ProductReviewGate())->register();
        });
    }
}
