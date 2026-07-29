<?php

declare(strict_types=1);

namespace Seviye\Commerce;

use Seviye\Commerce\Http\WooCommerceCartHooks;
use Seviye\Commerce\Support\CartPricingService;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Support\Environment;
use Seviye\Pricing\Contracts\PriceResolverInterface;
use Seviye\Students\Contracts\StudentGuardianCheckInterface;
use Seviye\Students\Contracts\StudentLookupInterface;

/**
 * Third module (after Students, Pricing) to depend on other modules'
 * Contracts exclusively - seviye/commerce has no Domain/Repository/REST
 * layer of its own in this milestone, it only orchestrates Students' and
 * Pricing's published Contracts against WooCommerce's own hooks. See
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

        if (!Environment::isWooCommerceActive()) {
            return;
        }

        $hooks = new WooCommerceCartHooks(
            $container->get(CartPricingService::class),
            $container->get(StudentLookupInterface::class)
        );
        $hooks->register();
    }
}
