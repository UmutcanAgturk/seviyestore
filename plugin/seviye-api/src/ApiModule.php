<?php

declare(strict_types=1);

namespace Seviye\Api;

use Seviye\Api\Auth\ApiKeyAuthenticator;
use Seviye\Api\Http\ApiKeyAuthHook;
use Seviye\Api\Http\ApiKeysRestController;
use Seviye\Api\Rbac\ApiCapability;
use Seviye\Api\Repository\ApiKeyRepositoryInterface;
use Seviye\Api\Repository\WpdbApiKeyRepository;
use Seviye\Api\Database\Migrations\CreateApiKeysTable;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Security\RateLimiter;

/**
 * Depends only on Core. Deliberately does NOT add any new business REST
 * endpoints of its own (beyond api-keys management) - every module already
 * owns its own seviye/v1/* routes; this module's entire job is making those
 * SAME routes reachable via an API key instead of a browser cookie+nonce
 * session, for external ERP/muhasebe/mobil integrations. See
 * docs/ARCHITECTURE.md's Seviye API section for the full scope reasoning.
 */
final class ApiModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'api';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            ApiKeyRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbApiKeyRepository => new WpdbApiKeyRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateApiKeysTable());

        $authenticator = new ApiKeyAuthenticator(
            $container->get(ApiKeyRepositoryInterface::class),
            $container->get(RateLimiter::class)
        );
        (new ApiKeyAuthHook($authenticator))->register();

        $container->get(RbacManager::class)->grantCapability(
            Role::GENEL_MERKEZ,
            ApiCapability::MANAGE_API_KEYS->value
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): ApiKeysRestController => new ApiKeysRestController(
                $container->get(ApiKeyRepositoryInterface::class)
            )
        );
    }
}
