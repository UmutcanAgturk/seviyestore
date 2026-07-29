<?php

declare(strict_types=1);

namespace Seviye\Pricing;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Pricing\Contracts\PriceResolverInterface;
use Seviye\Pricing\Database\Migrations\CreatePriceRulesTable;
use Seviye\Pricing\Http\PricingRestController;
use Seviye\Pricing\Rbac\PricingCapability;
use Seviye\Pricing\Repository\PriceRuleRepositoryInterface;
use Seviye\Pricing\Repository\WpdbPriceRuleRepository;
use Seviye\Pricing\Support\PriceResolver;
use Seviye\Students\Contracts\StudentLookupInterface;

/**
 * Second module (after Students) to depend on another module's Contracts -
 * here, both Branches' (BranchLookupInterface, BranchMembershipInterface)
 * and Students' (StudentLookupInterface). See docs/ARCHITECTURE.md, "Kural".
 */
final class PricingModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'pricing';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            PriceRuleRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbPriceRuleRepository => new WpdbPriceRuleRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            PriceResolverInterface::class,
            static fn (ServiceContainer $c): PriceResolver => new PriceResolver(
                $c->get(PriceRuleRepositoryInterface::class),
                $c->get(StudentLookupInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreatePriceRulesTable());

        $rbac = $container->get(RbacManager::class);
        $rbac->grantCapability(Role::GENEL_MERKEZ, PricingCapability::MANAGE_PRICING->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, PricingCapability::MANAGE_PRICING->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, PricingCapability::MANAGE_PRICING->value);

        $container->get(RestApiRegistrar::class)->register(
            static fn (): PricingRestController => new PricingRestController(
                $container->get(PriceRuleRepositoryInterface::class),
                $container->get(BranchMembershipInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(StudentLookupInterface::class)
            )
        );
    }
}
