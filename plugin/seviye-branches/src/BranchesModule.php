<?php

declare(strict_types=1);

namespace Seviye\Branches;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Branches\Database\Migrations\CreateBranchesTable;
use Seviye\Branches\Database\Migrations\CreateBranchUsersTable;
use Seviye\Branches\Http\BranchesRestController;
use Seviye\Branches\Rbac\BranchCapability;
use Seviye\Branches\Repository\BranchRepositoryInterface;
use Seviye\Branches\Repository\WpdbBranchLookup;
use Seviye\Branches\Repository\WpdbBranchMembershipRepository;
use Seviye\Branches\Repository\WpdbBranchRepository;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;

final class BranchesModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'branches';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            BranchRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbBranchRepository => new WpdbBranchRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            BranchMembershipInterface::class,
            static fn (ServiceContainer $c): WpdbBranchMembershipRepository => new WpdbBranchMembershipRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            BranchLookupInterface::class,
            static fn (ServiceContainer $c): WpdbBranchLookup => new WpdbBranchLookup(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateBranchesTable());
        $container->get(MigrationRunner::class)->register(new CreateBranchUsersTable());

        $rbac = $container->get(RbacManager::class);
        $rbac->grantCapability(Role::GENEL_MERKEZ, BranchCapability::MANAGE_BRANCHES->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, BranchCapability::MANAGE_BRANCHES->value);

        foreach ($this->branchScopedRoles() as $role) {
            $rbac->grantCapability($role, BranchCapability::VIEW_OWN_BRANCH->value);
        }

        $container->get(RestApiRegistrar::class)->register(new BranchesRestController(
            $container->get(BranchRepositoryInterface::class),
            $container->get(BranchMembershipInterface::class)
        ));
    }

    /**
     * @return list<Role>
     */
    private function branchScopedRoles(): array
    {
        return [
            Role::SUBE_MUDURU,
            Role::MUHASEBE,
            Role::DEPO,
            Role::SATIS_DANISMANI,
            Role::REHBERLIK,
        ];
    }
}
