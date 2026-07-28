<?php

declare(strict_types=1);

namespace Seviye\Branches\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Branches\BranchesModule::boot()} - Branches never touches
 * WordPress' role API directly.
 */
enum BranchCapability: string
{
    /** Genel Merkez / Bölge Müdürü: see and manage every branch. */
    case MANAGE_BRANCHES = 'scp_manage_branches';

    /** Branch-scoped staff roles: see only the branch they are assigned to. */
    case VIEW_OWN_BRANCH = 'scp_view_own_branch';
}
