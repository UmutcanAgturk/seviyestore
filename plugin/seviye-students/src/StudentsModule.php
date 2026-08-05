<?php

declare(strict_types=1);

namespace Seviye\Students;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Students\Contracts\BranchParentLookupInterface;
use Seviye\Students\Contracts\ParentChildrenLookupInterface;
use Seviye\Students\Contracts\ParentBranchLookupInterface;
use Seviye\Students\Contracts\ParentClassLookupInterface;
use Seviye\Students\Contracts\StudentDirectoryInterface;
use Seviye\Students\Contracts\StudentGuardianCheckInterface;
use Seviye\Students\Contracts\StudentLookupInterface;
use Seviye\Students\Database\Migrations\CreateStudentParentsTable;
use Seviye\Students\Database\Migrations\CreateStudentsTable;
use Seviye\Students\Http\StudentsRestController;
use Seviye\Students\Rbac\StudentCapability;
use Seviye\Students\Repository\StudentParentRepositoryInterface;
use Seviye\Students\Repository\StudentRepositoryInterface;
use Seviye\Students\Repository\WpdbBranchParentLookup;
use Seviye\Students\Repository\WpdbParentBranchLookup;
use Seviye\Students\Repository\WpdbParentChildrenLookup;
use Seviye\Students\Repository\WpdbParentClassLookup;
use Seviye\Students\Repository\WpdbStudentDirectory;
use Seviye\Students\Repository\WpdbStudentGuardianCheck;
use Seviye\Students\Repository\WpdbStudentLookup;
use Seviye\Students\Repository\WpdbStudentParentRepository;
use Seviye\Students\Repository\WpdbStudentRepository;
use Seviye\Students\Support\StudentImportParser;

/**
 * First module to depend on another module's Contracts (Branches'), not
 * just Core's - see docs/ARCHITECTURE.md, "Kural".
 */
final class StudentsModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'students';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            StudentRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbStudentRepository => new WpdbStudentRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            StudentParentRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbStudentParentRepository => new WpdbStudentParentRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            StudentLookupInterface::class,
            static fn (ServiceContainer $c): WpdbStudentLookup => new WpdbStudentLookup(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            StudentGuardianCheckInterface::class,
            static fn (ServiceContainer $c): WpdbStudentGuardianCheck => new WpdbStudentGuardianCheck(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            StudentDirectoryInterface::class,
            static fn (ServiceContainer $c): WpdbStudentDirectory => new WpdbStudentDirectory(
                $c->get(StudentRepositoryInterface::class)
            )
        );

        $container->singleton(
            ParentBranchLookupInterface::class,
            static fn (ServiceContainer $c): WpdbParentBranchLookup => new WpdbParentBranchLookup(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            BranchParentLookupInterface::class,
            static fn (ServiceContainer $c): WpdbBranchParentLookup => new WpdbBranchParentLookup(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            ParentClassLookupInterface::class,
            static fn (ServiceContainer $c): WpdbParentClassLookup => new WpdbParentClassLookup(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            ParentChildrenLookupInterface::class,
            static fn (ServiceContainer $c): WpdbParentChildrenLookup => new WpdbParentChildrenLookup(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateStudentsTable());
        $container->get(MigrationRunner::class)->register(new CreateStudentParentsTable());

        $rbac = $container->get(RbacManager::class);
        $rbac->grantCapability(Role::GENEL_MERKEZ, StudentCapability::MANAGE_STUDENTS->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, StudentCapability::MANAGE_STUDENTS->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, StudentCapability::MANAGE_STUDENTS->value);
        $rbac->grantCapability(Role::VELI, StudentCapability::VIEW_OWN_CHILDREN->value);

        $container->singleton(
            StudentImportParser::class,
            static fn (): StudentImportParser => new StudentImportParser()
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): StudentsRestController => new StudentsRestController(
                $container->get(StudentRepositoryInterface::class),
                $container->get(StudentParentRepositoryInterface::class),
                $container->get(BranchMembershipInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(StudentImportParser::class)
            )
        );
    }
}
