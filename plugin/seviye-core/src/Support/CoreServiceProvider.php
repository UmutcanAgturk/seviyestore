<?php

declare(strict_types=1);

namespace Seviye\Core\Support;

use Psr\Log\LoggerInterface;
use Seviye\Core\Cache\CacheInterface;
use Seviye\Core\Cache\TransientCache;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Database\Migrations\CreateLogsTable;
use Seviye\Core\Database\Migrations\CreateSettingsTable;
use Seviye\Core\Database\WpdbConnection;
use Seviye\Core\Events\EventBus;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Logging\DatabaseLogger;
use Seviye\Core\Module\ModuleRegistry;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\RoleGatewayInterface;
use Seviye\Core\Rbac\RoleRegistrar;
use Seviye\Core\Rbac\WpRoleGateway;
use Seviye\Core\Security\RateLimiter;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use Seviye\Core\Settings\WpdbSettingsRepository;

/**
 * Binds every Core service into the container and registers Core's own
 * (cross-cutting) migrations. Modules register their own migrations,
 * capabilities and listeners from within their own boot() method.
 */
final class CoreServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->singleton(EventBusInterface::class, static fn (): EventBus => new EventBus());

        $container->singleton(ConnectionInterface::class, static fn (): WpdbConnection => new WpdbConnection());

        $container->singleton(CacheInterface::class, static fn (): TransientCache => new TransientCache());

        $container->singleton(
            LoggerInterface::class,
            static fn (ServiceContainer $c): DatabaseLogger => new DatabaseLogger($c->get(ConnectionInterface::class))
        );

        $container->singleton(
            MigrationRunner::class,
            static fn (ServiceContainer $c): MigrationRunner => new MigrationRunner(
                $c->get(ConnectionInterface::class),
                $c->get(LoggerInterface::class)
            )
        );

        $container->singleton(RoleGatewayInterface::class, static fn (): WpRoleGateway => new WpRoleGateway());

        $container->singleton(
            RbacManager::class,
            static fn (ServiceContainer $c): RbacManager => new RbacManager($c->get(RoleGatewayInterface::class))
        );

        $container->singleton(
            RoleRegistrar::class,
            static fn (ServiceContainer $c): RoleRegistrar => new RoleRegistrar($c->get(RoleGatewayInterface::class))
        );

        $container->singleton(
            RateLimiter::class,
            static fn (ServiceContainer $c): RateLimiter => new RateLimiter($c->get(CacheInterface::class))
        );

        $container->singleton(RestApiRegistrar::class, static fn (): RestApiRegistrar => new RestApiRegistrar());

        $container->singleton(
            SettingsRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbSettingsRepository => new WpdbSettingsRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(ModuleRegistry::class, static fn (): ModuleRegistry => new ModuleRegistry());

        $container->get(MigrationRunner::class)->register(new CreateLogsTable());
        $container->get(MigrationRunner::class)->register(new CreateSettingsTable());
    }
}
