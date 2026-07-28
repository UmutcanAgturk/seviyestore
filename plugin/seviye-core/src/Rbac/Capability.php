<?php

declare(strict_types=1);

namespace Seviye\Core\Rbac;

/**
 * Core-owned, platform-wide capabilities. Module-specific capabilities
 * (e.g. "manage students") are defined by their owning module and granted to
 * roles at runtime through {@see RbacManager::grantCapability()}; they are
 * intentionally not enumerated here so Core never has to know about them.
 */
enum Capability: string
{
    case ACCESS_HQ_PANEL = 'scp_access_hq_panel';
    case ACCESS_BRANCH_PANEL = 'scp_access_branch_panel';
    case ACCESS_PARENT_PANEL = 'scp_access_parent_panel';
    case VIEW_ALL_BRANCHES = 'scp_view_all_branches';
    case MANAGE_CORE_SETTINGS = 'scp_manage_core_settings';
    case VIEW_AUDIT_LOGS = 'scp_view_audit_logs';
}
