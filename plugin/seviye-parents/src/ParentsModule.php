<?php

declare(strict_types=1);

namespace Seviye\Parents;

use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Parents\Contracts\ParentContactLookupInterface;
use Seviye\Parents\Database\Migrations\CreateParentProfilesTable;
use Seviye\Parents\Http\ParentProfileRestController;
use Seviye\Parents\Rbac\ParentCapability;
use Seviye\Parents\Repository\ParentProfileRepositoryInterface;
use Seviye\Parents\Repository\WpdbParentContactLookup;
use Seviye\Parents\Repository\WpdbParentProfileRepository;

/**
 * Unlike Students, this module depends only on Core - "Kendi öğrencileri"
 * is already served by Seviye Students' own /students/mine endpoint, so
 * Parents never needs to read Students' or Branches' data at all.
 */
final class ParentsModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'parents';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            ParentProfileRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbParentProfileRepository => new WpdbParentProfileRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            ParentContactLookupInterface::class,
            static fn (ServiceContainer $c): WpdbParentContactLookup => new WpdbParentContactLookup(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateParentProfilesTable());

        $container->get(RbacManager::class)->grantCapability(Role::VELI, ParentCapability::MANAGE_OWN_PROFILE->value);

        $container->get(RestApiRegistrar::class)->register(
            static fn (): ParentProfileRestController => new ParentProfileRestController(
                $container->get(ParentProfileRepositoryInterface::class)
            )
        );
    }
}
