<?php

declare(strict_types=1);

namespace Seviye\Core\Rbac;

/**
 * Runtime RBAC facade used by Core and by modules. Modules extend a
 * Core-defined role with their own capabilities through this manager instead
 * of touching WordPress' role API directly, keeping "modules talk to Core,
 * never to each other" true for permissions as well.
 */
final class RbacManager
{
    public function __construct(private readonly RoleGatewayInterface $gateway)
    {
    }

    public function grantCapability(Role $role, string $capability): void
    {
        $this->gateway->addCapability($role->value, $capability);
    }

    public function currentUserHasRole(Role $role): bool
    {
        if (!function_exists('wp_get_current_user')) {
            return false;
        }

        return in_array($role->value, wp_get_current_user()->roles, true);
    }

    public function currentUserCan(Capability|string $capability): bool
    {
        if (!function_exists('current_user_can')) {
            return false;
        }

        return current_user_can($capability instanceof Capability ? $capability->value : $capability);
    }
}
