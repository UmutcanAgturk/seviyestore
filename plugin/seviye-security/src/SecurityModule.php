<?php

declare(strict_types=1);

namespace Seviye\Security;

use Psr\Log\LoggerInterface;
use Seviye\Core\Cache\CacheInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Security\RateLimiter;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use Seviye\Security\Auth\AuthService;
use Seviye\Security\Auth\CredentialGatewayInterface;
use Seviye\Security\Auth\WpCredentialGateway;
use Seviye\Security\Database\Migrations\CreatePasswordTokensTable;
use Seviye\Security\Database\Migrations\CreateTwoFactorSecretsTable;
use Seviye\Security\Database\Migrations\CreateUserIdentitiesTable;
use Seviye\Security\Http\Admin\UserAuthorizationAdminPage;
use Seviye\Security\Http\AuthRestController;
use Seviye\Security\Http\SecuritySettingsRestController;
use Seviye\Security\Http\TwoFactorRestController;
use Seviye\Security\Identity\IdentityGatewayInterface;
use Seviye\Security\Identity\WpdbIdentityGateway;
use Seviye\Security\Rbac\SecurityCapability;
use Seviye\Security\Token\ClockInterface;
use Seviye\Security\Token\PasswordTokenGatewayInterface;
use Seviye\Security\Token\PasswordTokenService;
use Seviye\Security\Token\SystemClock;
use Seviye\Security\Token\WpdbPasswordTokenGateway;
use Seviye\Security\TwoFactor\Encryptor;
use Seviye\Security\TwoFactor\PendingTwoFactorLoginService;
use Seviye\Security\TwoFactor\TwoFactorGatewayInterface;
use Seviye\Security\TwoFactor\TwoFactorService;
use Seviye\Security\TwoFactor\WpdbTwoFactorGateway;

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

        $container->singleton(
            Encryptor::class,
            static fn (): Encryptor => Encryptor::fromSecret(wp_salt('secure_auth'))
        );

        $container->singleton(
            TwoFactorGatewayInterface::class,
            static fn (ServiceContainer $c): WpdbTwoFactorGateway => new WpdbTwoFactorGateway(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            TwoFactorService::class,
            static fn (ServiceContainer $c): TwoFactorService => new TwoFactorService(
                $c->get(TwoFactorGatewayInterface::class),
                $c->get(Encryptor::class)
            )
        );

        $container->singleton(
            PendingTwoFactorLoginService::class,
            static fn (ServiceContainer $c): PendingTwoFactorLoginService => new PendingTwoFactorLoginService(
                $c->get(CacheInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateUserIdentitiesTable());
        $container->get(MigrationRunner::class)->register(new CreatePasswordTokensTable());
        $container->get(MigrationRunner::class)->register(new CreateTwoFactorSecretsTable());

        $container->get(RestApiRegistrar::class)->register(static fn (): AuthRestController => new AuthRestController(
            $container->get(AuthService::class),
            $container->get(IdentityGatewayInterface::class),
            $container->get(PasswordTokenService::class),
            $container->get(EventBusInterface::class),
            $container->get(RateLimiter::class),
            $container->get(TwoFactorService::class),
            $container->get(PendingTwoFactorLoginService::class)
        ));

        $container->get(RestApiRegistrar::class)->register(
            static fn (): TwoFactorRestController => new TwoFactorRestController(
                $container->get(TwoFactorService::class),
                $container->get(CredentialGatewayInterface::class)
            )
        );

        $container->get(RbacManager::class)->grantCapability(
            Role::GENEL_MERKEZ,
            SecurityCapability::MANAGE_SECURITY_SETTINGS->value
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): SecuritySettingsRestController => new SecuritySettingsRestController(
                $container->get(SettingsRepositoryInterface::class)
            )
        );

        (new UserAuthorizationAdminPage($container->get(IdentityGatewayInterface::class)))->register();
    }
}
