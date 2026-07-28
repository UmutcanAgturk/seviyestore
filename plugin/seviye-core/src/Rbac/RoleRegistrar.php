<?php

declare(strict_types=1);

namespace Seviye\Core\Rbac;

/**
 * Registers/updates the nine platform roles on plugin activation.
 */
final class RoleRegistrar
{
    public function __construct(private readonly RoleGatewayInterface $gateway)
    {
    }

    public function register(): void
    {
        $defaults = RoleDefinitions::defaults();

        foreach (Role::cases() as $role) {
            $capabilities = array_fill_keys(
                array_map(static fn (Capability $capability): string => $capability->value, $defaults[$role->value]),
                true
            );

            if ($this->gateway->roleExists($role->value)) {
                foreach (array_keys($capabilities) as $capability) {
                    $this->gateway->addCapability($role->value, $capability);
                }

                continue;
            }

            $this->gateway->addRole($role->value, $role->label(), $capabilities);
        }
    }

    /**
     * Not called automatically on deactivation/uninstall: removing roles
     * would strand any users already assigned to them. Reserved for an
     * explicit, deliberate cleanup command.
     */
    public function deregister(): void
    {
        foreach (Role::cases() as $role) {
            $this->gateway->removeRole($role->value);
        }
    }
}
