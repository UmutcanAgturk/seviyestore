<?php

declare(strict_types=1);

namespace Seviye\Security\Http\Admin;

use Seviye\Security\Rbac\SecurityCapability;

/**
 * Single source of truth for the capability gating every "Seviye
 * Kullanıcılar" wp-admin page. Deliberately `SecurityCapability::MANAGE_SECURITY_SETTINGS`
 * rather than `manage_options`: {@see \Seviye\Security\SecurityModule::boot()}
 * grants this capability to BOTH Role::GENEL_MERKEZ and WordPress' native
 * `administrator` role, so the pages are reachable by Genel Merkez staff
 * (who have no native `edit_users`/`manage_options`) as well as the site's
 * real WordPress administrator - one capability, one menu tree, both
 * audiences.
 */
final class AdminAccess
{
    public static function capability(): string
    {
        return SecurityCapability::MANAGE_SECURITY_SETTINGS->value;
    }

    public static function current(): bool
    {
        return current_user_can(self::capability());
    }
}
