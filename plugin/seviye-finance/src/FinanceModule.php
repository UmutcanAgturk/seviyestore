<?php

declare(strict_types=1);

namespace Seviye\Finance;

use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Finance\Database\Migrations\CreateHakedisEntriesTable;
use Seviye\Finance\Repository\HakedisRepositoryInterface;
use Seviye\Finance\Repository\WpdbHakedisRepository;
use Seviye\Finance\Support\HakedisEventListener;

/**
 * Unlike every module so far, seviye/finance depends on no other module's
 * Contracts at all - only Core's. It listens for `commerce.order_line_item_completed`/
 * `_reversed` purely through Core's EventBusInterface, a documented event
 * name/payload contract rather than a PHP interface - see
 * docs/ARCHITECTURE.md, bölüm 15. This means Finance can be installed and
 * will work correctly whether or not Seviye Commerce happens to be active;
 * it simply records nothing until events start arriving.
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

        $container->get(MigrationRunner::class)->register(new CreateHakedisEntriesTable());

        $listener = new HakedisEventListener($container->get(HakedisRepositoryInterface::class));
        $eventBus = $container->get(EventBusInterface::class);
        $eventBus->listen('commerce.order_line_item_completed', [$listener, 'onOrderLineItemCompleted']);
        $eventBus->listen('commerce.order_line_item_reversed', [$listener, 'onOrderLineItemReversed']);
    }
}
