<?php

declare(strict_types=1);

namespace Seviye\Commerce;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Commerce\Database\Migrations\CreateOrderLineItemsTable;
use Seviye\Commerce\Http\OrderPersistenceHooks;
use Seviye\Commerce\Http\WooCommerceCartHooks;
use Seviye\Commerce\Repository\OrderLineItemRepositoryInterface;
use Seviye\Commerce\Repository\WpdbOrderLineItemRepository;
use Seviye\Commerce\Support\CartPricingService;
use Seviye\Commerce\Support\SplitPaymentCalculator;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Support\Environment;
use Seviye\Pricing\Contracts\PriceResolverInterface;
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

        $container->get(MigrationRunner::class)->register(new CreateOrderLineItemsTable());

        if (!Environment::isWooCommerceActive()) {
            return;
        }

        $cartHooks = new WooCommerceCartHooks(
            $container->get(CartPricingService::class),
            $container->get(StudentLookupInterface::class)
        );
        $cartHooks->register();

        $orderHooks = new OrderPersistenceHooks(
            $container->get(OrderLineItemRepositoryInterface::class),
            $container->get(StudentLookupInterface::class),
            $container->get(BranchLookupInterface::class),
            new SplitPaymentCalculator(),
            $container->get(EventBusInterface::class)
        );
        $orderHooks->register();
    }
}
