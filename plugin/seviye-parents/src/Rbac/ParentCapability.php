<?php

declare(strict_types=1);

namespace Seviye\Parents\Rbac;

enum ParentCapability: string
{
    /** Veli: view/update their own profile (phone, notification preference, KVKK consent). */
    case MANAGE_OWN_PROFILE = 'scp_manage_own_profile';
}
