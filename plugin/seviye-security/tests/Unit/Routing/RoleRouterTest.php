<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Rbac\Role;
use Seviye\Security\Routing\RoleRouter;

final class RoleRouterTest extends TestCase
{
    public function testHeadquartersRolesLandOnAdmin(): void
    {
        self::assertSame('/admin', RoleRouter::landingPathFor([Role::GENEL_MERKEZ->value]));
        self::assertSame('/admin', RoleRouter::landingPathFor([Role::BOLGE_MUDURU->value]));
    }

    public function testBranchRolesLandOnSube(): void
    {
        self::assertSame('/sube', RoleRouter::landingPathFor([Role::SUBE_MUDURU->value]));
        self::assertSame('/sube', RoleRouter::landingPathFor([Role::MUHASEBE->value]));
        self::assertSame('/sube', RoleRouter::landingPathFor([Role::DEPO->value]));
        self::assertSame('/sube', RoleRouter::landingPathFor([Role::SATIS_DANISMANI->value]));
        self::assertSame('/sube', RoleRouter::landingPathFor([Role::REHBERLIK->value]));
    }

    public function testParentRoleAndUnknownRolesLandOnRoot(): void
    {
        self::assertSame('/', RoleRouter::landingPathFor([Role::VELI->value]));
        self::assertSame('/', RoleRouter::landingPathFor([]));
        self::assertSame('/', RoleRouter::landingPathFor(['some_unrelated_wp_role']));
    }

    public function testIsPathAllowedForRolesMatchesTheOwningZone(): void
    {
        self::assertTrue(RoleRouter::isPathAllowedForRoles('/admin', [Role::GENEL_MERKEZ->value]));
        self::assertTrue(RoleRouter::isPathAllowedForRoles('/admin/branches?x=1', [Role::BOLGE_MUDURU->value]));
        self::assertTrue(RoleRouter::isPathAllowedForRoles('/sube', [Role::SUBE_MUDURU->value]));
        self::assertTrue(RoleRouter::isPathAllowedForRoles('/sube/orders', [Role::DEPO->value]));
        self::assertTrue(RoleRouter::isPathAllowedForRoles('/', [Role::VELI->value]));
        self::assertTrue(RoleRouter::isPathAllowedForRoles('/kitaplar', [Role::VELI->value]));
    }

    public function testIsPathAllowedForRolesRejectsCrossZoneAccess(): void
    {
        self::assertFalse(RoleRouter::isPathAllowedForRoles('/admin', [Role::VELI->value]));
        self::assertFalse(RoleRouter::isPathAllowedForRoles('/sube', [Role::GENEL_MERKEZ->value]));
        self::assertFalse(RoleRouter::isPathAllowedForRoles('/', [Role::SUBE_MUDURU->value]));
    }

    public function testPathsWithoutALeadingSlashAreNormalized(): void
    {
        self::assertTrue(RoleRouter::isPathAllowedForRoles('admin/dashboard', [Role::GENEL_MERKEZ->value]));
    }
}
