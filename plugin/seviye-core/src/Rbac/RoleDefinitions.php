<?php

declare(strict_types=1);

namespace Seviye\Core\Rbac;

/**
 * Default, Core-level capability grants for each {@see Role}. This only
 * covers panel-access-level capabilities; modules layer their own
 * capabilities on top at boot time.
 */
final class RoleDefinitions
{
    /**
     * @return array<string, list<Capability>>
     */
    public static function defaults(): array
    {
        return [
            Role::GENEL_MERKEZ->value => [
                Capability::ACCESS_HQ_PANEL,
                Capability::VIEW_ALL_BRANCHES,
                Capability::MANAGE_CORE_SETTINGS,
                Capability::VIEW_AUDIT_LOGS,
            ],
            Role::BOLGE_MUDURU->value => [
                Capability::ACCESS_HQ_PANEL,
                Capability::VIEW_ALL_BRANCHES,
            ],
            Role::SUBE_MUDURU->value => [
                Capability::ACCESS_BRANCH_PANEL,
            ],
            Role::MUHASEBE->value => [
                Capability::ACCESS_BRANCH_PANEL,
            ],
            Role::DEPO->value => [
                Capability::ACCESS_BRANCH_PANEL,
            ],
            Role::SATIS_DANISMANI->value => [
                Capability::ACCESS_BRANCH_PANEL,
            ],
            Role::REHBERLIK->value => [
                Capability::ACCESS_BRANCH_PANEL,
            ],
            Role::VELI->value => [
                Capability::ACCESS_PARENT_PANEL,
            ],
            Role::SISTEM->value => [],
        ];
    }
}
