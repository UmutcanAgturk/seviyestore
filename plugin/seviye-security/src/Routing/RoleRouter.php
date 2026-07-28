<?php

declare(strict_types=1);

namespace Seviye\Security\Routing;

use Seviye\Core\Rbac\Role;

/**
 * Maps a user's WordPress roles to the URL zone they belong to, per the
 * platform's fixed entry points: store.seviye.com.tr (Veli), /sube (Şube
 * Paneli), /admin (Genel Merkez). Each role belongs to exactly one zone in
 * this milestone; a role that needs to work across zones (e.g. Genel Merkez
 * browsing a specific branch's data) is a Branches-module-era concern, not
 * a routing one, and will be modelled there when that module exists.
 */
final class RoleRouter
{
    private const ADMIN_PATH = '/admin';
    private const BRANCH_PATH = '/sube';
    private const PARENT_PATH = '/';

    /**
     * @param list<string> $roleSlugs
     */
    public static function landingPathFor(array $roleSlugs): string
    {
        return match (self::zoneFor($roleSlugs)) {
            'admin' => self::ADMIN_PATH,
            'branch' => self::BRANCH_PATH,
            default => self::PARENT_PATH,
        };
    }

    /**
     * @param list<string> $roleSlugs
     */
    public static function isPathAllowedForRoles(string $path, array $roleSlugs): bool
    {
        return self::zoneFor($roleSlugs) === self::zoneForPath($path);
    }

    /**
     * @param list<string> $roleSlugs
     */
    private static function zoneFor(array $roleSlugs): string
    {
        $adminRoles = [Role::GENEL_MERKEZ->value, Role::BOLGE_MUDURU->value];

        if (array_intersect($roleSlugs, $adminRoles) !== []) {
            return 'admin';
        }

        $branchRoles = [
            Role::SUBE_MUDURU->value,
            Role::MUHASEBE->value,
            Role::DEPO->value,
            Role::SATIS_DANISMANI->value,
            Role::REHBERLIK->value,
        ];

        if (array_intersect($roleSlugs, $branchRoles) !== []) {
            return 'branch';
        }

        return 'parent';
    }

    private static function zoneForPath(string $path): string
    {
        $normalized = '/' . trim((string) parse_url($path, PHP_URL_PATH), '/');

        if ($normalized === self::ADMIN_PATH || str_starts_with($normalized, self::ADMIN_PATH . '/')) {
            return 'admin';
        }

        if ($normalized === self::BRANCH_PATH || str_starts_with($normalized, self::BRANCH_PATH . '/')) {
            return 'branch';
        }

        return 'parent';
    }
}
