<?php

declare(strict_types=1);

namespace Seviye\Core\Rbac;

/**
 * Port abstracting WordPress' role/capability API so RBAC orchestration
 * logic ({@see RoleRegistrar}) can be unit tested without WordPress loaded.
 */
interface RoleGatewayInterface
{
    /**
     * @param array<string, bool> $capabilities
     */
    public function addRole(string $slug, string $displayName, array $capabilities): void;

    public function removeRole(string $slug): void;

    public function roleExists(string $slug): bool;

    public function addCapability(string $roleSlug, string $capability): void;
}
