<?php

declare(strict_types=1);

namespace Seviye\Destek;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Destek\Database\Migrations\CreateSupportMessagesTable;
use Seviye\Destek\Database\Migrations\CreateSupportTicketsTable;
use Seviye\Destek\Http\SupportTicketsRestController;
use Seviye\Destek\Rbac\SupportCapability;
use Seviye\Destek\Repository\SupportTicketRepositoryInterface;
use Seviye\Destek\Repository\WpdbSupportTicketRepository;
use Seviye\Students\Contracts\ParentChildrenLookupInterface;

/**
 * "Veli destek/talep (helpdesk) sistemi" - KVKK talebinden (Seviye
 * Security'nin PrivacyRequestsRestController'ı) tamamen ayrı bir modül:
 * genel şikayet/soru bildirme akışı. Branches'a (şube kapsamı) ve
 * Students'a (ParentChildrenLookupInterface - veli hangi şubelerin
 * çocuğuna sahip) bağımlı, aksi halde Students'ın kendisiyle aynı
 * bağımlılık grafiği.
 */
final class DestekModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'destek';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            SupportTicketRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbSupportTicketRepository => new WpdbSupportTicketRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateSupportTicketsTable());
        $container->get(MigrationRunner::class)->register(new CreateSupportMessagesTable());

        $rbac = $container->get(RbacManager::class);
        $rbac->grantCapability(Role::VELI, SupportCapability::SUBMIT_TICKET->value);

        foreach ([Role::GENEL_MERKEZ, Role::BOLGE_MUDURU, Role::SUBE_MUDURU, Role::REHBERLIK] as $role) {
            $rbac->grantCapability($role, SupportCapability::MANAGE_TICKETS->value);
        }

        $container->get(RestApiRegistrar::class)->register(
            static fn (): SupportTicketsRestController => new SupportTicketsRestController(
                $container->get(SupportTicketRepositoryInterface::class),
                $container->get(BranchMembershipInterface::class),
                $container->get(BranchLookupInterface::class),
                $container->get(ParentChildrenLookupInterface::class)
            )
        );
    }
}
