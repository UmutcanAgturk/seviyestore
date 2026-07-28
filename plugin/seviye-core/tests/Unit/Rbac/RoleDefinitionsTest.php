<?php

declare(strict_types=1);

namespace Seviye\Core\Tests\Unit\Rbac;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Rbac\Capability;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Rbac\RoleDefinitions;

final class RoleDefinitionsTest extends TestCase
{
    public function testEveryRoleHasADefaultCapabilityEntry(): void
    {
        $defaults = RoleDefinitions::defaults();

        foreach (Role::cases() as $role) {
            self::assertArrayHasKey($role->value, $defaults);

            foreach ($defaults[$role->value] as $capability) {
                self::assertInstanceOf(Capability::class, $capability);
            }
        }
    }

    public function testHeadquartersRoleCanViewAllBranches(): void
    {
        $defaults = RoleDefinitions::defaults();

        self::assertContains(Capability::VIEW_ALL_BRANCHES, $defaults[Role::GENEL_MERKEZ->value]);
    }

    public function testSystemRoleHasNoDefaultCapabilities(): void
    {
        $defaults = RoleDefinitions::defaults();

        self::assertSame([], $defaults[Role::SISTEM->value]);
    }
}
