<?php

declare(strict_types=1);

namespace Seviye\Finance;

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
use Seviye\Finance\Database\Migrations\CreateHakedisEntriesTable;
use Seviye\Finance\Database\Migrations\CreateHakedisSettlementsTable;
use Seviye\Finance\Http\HakedisRestController;
use Seviye\Finance\Rbac\HakedisCapability;
use Seviye\Finance\Repository\HakedisRepositoryInterface;
use Seviye\Finance\Repository\SettlementRepositoryInterface;
use Seviye\Finance\Repository\WpdbHakedisRepository;
use Seviye\Finance\Repository\WpdbSettlementRepository;
use Seviye\Finance\Support\HakedisEventListener;

/**
 * The ledger itself (Support\HakedisEventListener) depends on no other
 * module's Contracts at all - only Core's, listening for
 * `commerce.order_line_item_completed`/`_reversed` purely through Core's
 * EventBusInterface, a documented event name/payload contract rather than
 * a PHP interface (see docs/ARCHITECTURE.md, bölüm 15). It works correctly
 * whether or not Seviye Commerce happens to be active - it simply records
 * nothing until events start arriving.
 *
 * The balance-viewing REST layer added here is a different story: "who may
 * see which branch's balance" is a real authorization question, so it
 * depends on Branches' Contracts (BranchMembershipInterface,
 * BranchLookupInterface) exactly like Students/Pricing/Commerce do.
 */
final class FinanceModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'finance';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            HakedisRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbHakedisRepository => new WpdbHakedisRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            SettlementRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbSettlementRepository => new WpdbSettlementRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateHakedisEntriesTable());
        $container->get(MigrationRunner::class)->register(new CreateHakedisSettlementsTable());

        $listener = new HakedisEventListener($container->get(HakedisRepositoryInterface::class));
        $eventBus = $container->get(EventBusInterface::class);
        $eventBus->listen('commerce.order_line_item_completed', [$listener, 'onOrderLineItemCompleted']);
        $eventBus->listen('commerce.order_line_item_reversed', [$listener, 'onOrderLineItemReversed']);

        $rbac = $container->get(RbacManager::class);
        $rbac->grantCapability(Role::GENEL_MERKEZ, HakedisCapability::VIEW_HAKEDIS->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, HakedisCapability::VIEW_HAKEDIS->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, HakedisCapability::VIEW_OWN_HAKEDIS->value);
        $rbac->grantCapability(Role::MUHASEBE, HakedisCapability::VIEW_OWN_HAKEDIS->value);
        $rbac->grantCapability(Role::GENEL_MERKEZ, HakedisCapability::RECORD_SETTLEMENT->value);
        $rbac->grantCapability(Role::MUHASEBE, HakedisCapability::RECORD_SETTLEMENT->value);

        $container->get(RestApiRegistrar::class)->register(
            static fn (): HakedisRestController => new HakedisRestController(
                $container->get(HakedisRepositoryInterface::class),
                $container->get(SettlementRepositoryInterface::class),
                $container->get(BranchMembershipInterface::class),
                $container->get(BranchLookupInterface::class)
            )
        );
    }
}
