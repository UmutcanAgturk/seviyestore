<?php

declare(strict_types=1);

namespace Seviye\Core\Rbac;

final class WpRoleGateway implements RoleGatewayInterface
{
    public function addRole(string $slug, string $displayName, array $capabilities): void
    {
        add_role($slug, $displayName, $capabilities);
    }

    public function removeRole(string $slug): void
    {
        remove_role($slug);
    }

    public function roleExists(string $slug): bool
    {
        return get_role($slug) !== null;
    }

    public function addCapability(string $roleSlug, string $capability): void
    {
        $role = get_role($roleSlug);
        $role?->add_cap($capability);
    }
}
