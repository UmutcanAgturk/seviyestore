<?php

declare(strict_types=1);

namespace Seviye\Security;

use Psr\Log\LoggerInterface;
use Seviye\Core\Cache\CacheInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Events\Event;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Security\RateLimiter;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use Seviye\Parents\Contracts\ParentContactLookupInterface;
use Seviye\Security\Auth\AuthService;
use Seviye\Security\Auth\CredentialGatewayInterface;
use Seviye\Security\Auth\MustChangePasswordGatewayInterface;
use Seviye\Security\Auth\WpCredentialGateway;
use Seviye\Security\Auth\WpdbMustChangePasswordGateway;
use Seviye\Security\Database\Migrations\CreateMustChangePasswordFlagsTable;
use Seviye\Security\Database\Migrations\CreatePasswordTokensTable;
use Seviye\Security\Database\Migrations\CreatePrivacyRequestsTable;
use Seviye\Security\Database\Migrations\CreateTwoFactorSecretsTable;
use Seviye\Security\Database\Migrations\CreateUserIdentitiesTable;
use Seviye\Security\Http\AccountRestController;
use Seviye\Security\Http\Admin\SeviyeUsersMenu;
use Seviye\Security\Http\Admin\StudentDirectoryPage;
use Seviye\Security\Http\Admin\UserAuthorizationAdminPage;
use Seviye\Security\Http\Admin\UserListPage;
use Seviye\Security\Http\AuthRestController;
use Seviye\Security\Http\IdentityRestController;
use Seviye\Security\Http\PrivacyRequestsRestController;
use Seviye\Security\Http\SecuritySettingsRestController;
use Seviye\Security\Http\TwoFactorRestController;
use Seviye\Security\Identity\IdentityGatewayInterface;
use Seviye\Security\Identity\WpdbIdentityGateway;
use Seviye\Security\Privacy\PrivacyExportBuilder;
use Seviye\Security\Privacy\PrivacyRequestGatewayInterface;
use Seviye\Security\Privacy\WpdbPrivacyRequestGateway;
use Seviye\Security\Rbac\PrivacyCapability;
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
use Seviye\Students\Contracts\ParentChildrenLookupInterface;

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
            MustChangePasswordGatewayInterface::class,
            static fn (ServiceContainer $c): WpdbMustChangePasswordGateway => new WpdbMustChangePasswordGateway(
                $c->get(ConnectionInterface::class)
            )
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

        $container->singleton(
            PrivacyRequestGatewayInterface::class,
            static fn (ServiceContainer $c): WpdbPrivacyRequestGateway => new WpdbPrivacyRequestGateway(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            PrivacyExportBuilder::class,
            static fn (): PrivacyExportBuilder => new PrivacyExportBuilder()
        );

        $container->get(MigrationRunner::class)->register(new CreateUserIdentitiesTable());
        $container->get(MigrationRunner::class)->register(new CreatePasswordTokensTable());
        $container->get(MigrationRunner::class)->register(new CreateTwoFactorSecretsTable());
        $container->get(MigrationRunner::class)->register(new CreatePrivacyRequestsTable());
        $container->get(MigrationRunner::class)->register(new CreateMustChangePasswordFlagsTable());

        $container->get(RestApiRegistrar::class)->register(static fn (): AuthRestController => new AuthRestController(
            $container->get(AuthService::class),
            $container->get(IdentityGatewayInterface::class),
            $container->get(PasswordTokenService::class),
            $container->get(EventBusInterface::class),
            $container->get(RateLimiter::class),
            $container->get(TwoFactorService::class),
            $container->get(PendingTwoFactorLoginService::class),
            $container->get(MustChangePasswordGatewayInterface::class)
        ));

        $container->get(RestApiRegistrar::class)->register(
            static fn (): TwoFactorRestController => new TwoFactorRestController(
                $container->get(TwoFactorService::class),
                $container->get(CredentialGatewayInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): AccountRestController => new AccountRestController(
                $container->get(CredentialGatewayInterface::class),
                $container->get(MustChangePasswordGatewayInterface::class)
            )
        );

        $container->get(RbacManager::class)->grantCapability(
            Role::GENEL_MERKEZ,
            SecurityCapability::MANAGE_SECURITY_SETTINGS->value
        );

        // WordPress' native `administrator` role isn't one of Core's nine
        // Role enum cases, so RbacManager::grantCapability() (which only
        // accepts a Role) can't grant it this capability - added directly
        // so the site's real WordPress administrator can reach the
        // "Seviye Kullanıcılar" menu tree too, alongside Genel Merkez.
        $administratorRole = get_role('administrator');

        if ($administratorRole !== null) {
            $administratorRole->add_cap(SecurityCapability::MANAGE_SECURITY_SETTINGS->value);
        }

        $container->get(RestApiRegistrar::class)->register(
            static fn (): SecuritySettingsRestController => new SecuritySettingsRestController(
                $container->get(SettingsRepositoryInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): IdentityRestController => new IdentityRestController(
                $container->get(IdentityGatewayInterface::class)
            )
        );

        // "KVKK: veri ihracı/silme talebi" - the closure below resolves
        // Seviye\Parents\Contracts\ParentContactLookupInterface and
        // Seviye\Students\Contracts\ParentChildrenLookupInterface, both new
        // dependencies (see composer.json) - safe to resolve directly here
        // (no add_action('init', ...) deferral needed) because
        // RestApiRegistrar only invokes this factory inside rest_api_init,
        // by which point every module has already booted regardless of
        // plugin registration order - see RestApiRegistrar's own docblock.
        $container->get(RestApiRegistrar::class)->register(
            static fn (): PrivacyRequestsRestController => new PrivacyRequestsRestController(
                $container->get(PrivacyRequestGatewayInterface::class),
                $container->get(IdentityGatewayInterface::class),
                $container->get(TwoFactorGatewayInterface::class),
                $container->get(ParentContactLookupInterface::class),
                $container->get(ParentChildrenLookupInterface::class),
                $container->get(PrivacyExportBuilder::class)
            )
        );

        $container->get(RbacManager::class)->grantCapability(
            Role::GENEL_MERKEZ,
            PrivacyCapability::MANAGE_PRIVACY_REQUESTS->value
        );

        $identities = $container->get(IdentityGatewayInterface::class);

        (new SeviyeUsersMenu(
            new UserListPage($identities, $container->get(MustChangePasswordGatewayInterface::class)),
            new UserAuthorizationAdminPage($identities),
            new StudentDirectoryPage($container)
        ))->register();

        // "Kurum tarafından oluşturulan şifreyle ilk giriş" - Students'
        // maybeCreateAndLinkParent() dispatches this when it auto-creates a
        // veli account with a generated password (see that method's own
        // docblock on why it can't call MustChangePasswordGatewayInterface
        // directly - the same Students→Security dependency-direction
        // constraint as every other cross-module EventBus listener in this
        // codebase). Registered directly in boot(), not deferred to `init`
        // like Notifications' listeners: the closure below only resolves
        // Security's OWN binding (registered earlier in this same boot()
        // call), never a Contract from a module that may not have booted
        // yet.
        $container->get(EventBusInterface::class)->listen(
            'students.parent_password_generated',
            static function (Event $event) use ($container): void {
                $container->get(MustChangePasswordGatewayInterface::class)->flag((int) $event->get('user_id'));
            }
        );
    }
}
