<?php

declare(strict_types=1);

namespace Seviye\Security;

use Psr\Log\LoggerInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Security\RateLimiter;
use Seviye\Security\Auth\AuthService;
use Seviye\Security\Auth\CredentialGatewayInterface;
use Seviye\Security\Auth\WpCredentialGateway;
use Seviye\Security\Database\Migrations\CreatePasswordTokensTable;
use Seviye\Security\Database\Migrations\CreateUserIdentitiesTable;
use Seviye\Security\Http\AuthRestController;
use Seviye\Security\Identity\IdentityGatewayInterface;
use Seviye\Security\Identity\WpdbIdentityGateway;
use Seviye\Security\Token\ClockInterface;
use Seviye\Security\Token\PasswordTokenGatewayInterface;
use Seviye\Security\Token\PasswordTokenService;
use Seviye\Security\Token\SystemClock;
use Seviye\Security\Token\WpdbPasswordTokenGateway;

/**
 * Binds Security's services into Core's shared container and registers its
 * migrations + REST routes. Also invoked directly (not just through the
 * normal module boot cycle) by {@see Support\Activator}, since on first
 * activation this plugin's own plugins_loaded registration has not run yet
 * within that same request - see docs/ARCHITECTURE.md.
 */
final class SecurityModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'security';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(ClockInterface::class, static fn (): SystemClock => new SystemClock());

        $container->singleton(
            IdentityGatewayInterface::class,
            static fn (ServiceContainer $c): WpdbIdentityGateway => new WpdbIdentityGateway(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            CredentialGatewayInterface::class,
            static fn (): WpCredentialGateway => new WpCredentialGateway()
        );

        $container->singleton(
            PasswordTokenGatewayInterface::class,
            static fn (ServiceContainer $c): WpdbPasswordTokenGateway => new WpdbPasswordTokenGateway(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            PasswordTokenService::class,
            static fn (ServiceContainer $c): PasswordTokenService => new PasswordTokenService(
                $c->get(PasswordTokenGatewayInterface::class),
                $c->get(ClockInterface::class)
            )
        );

        $container->singleton(
            AuthService::class,
            static fn (ServiceContainer $c): AuthService => new AuthService(
                $c->get(IdentityGatewayInterface::class),
                $c->get(CredentialGatewayInterface::class),
                $c->get(RateLimiter::class),
                $c->get(LoggerInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateUserIdentitiesTable());
        $container->get(MigrationRunner::class)->register(new CreatePasswordTokensTable());

        $container->get(RestApiRegistrar::class)->register(static fn (): AuthRestController => new AuthRestController(
            $container->get(AuthService::class),
            $container->get(IdentityGatewayInterface::class),
            $container->get(PasswordTokenService::class),
            $container->get(EventBusInterface::class),
            $container->get(RateLimiter::class)
        ));
    }
}
