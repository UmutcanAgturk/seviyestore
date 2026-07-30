<?php

declare(strict_types=1);

namespace Seviye\Security\Routing;

/**
 * Pure IP/CIDR matcher backing the /admin zone's IP restriction - kept
 * dependency-free and static, the same "policy stays unit-tested outside
 * WordPress" reasoning {@see RoleRouter} already documents for role→zone
 * routing. Supports both IPv4 and IPv6, exact addresses and CIDR ranges,
 * via inet_pton()'s binary form rather than string comparison - a plain
 * string compare would miss that "::1" and "0:0:0:0:0:0:0:1" are the same
 * IPv6 address.
 *
 * An empty allowlist means the feature is off (matches every request) -
 * IP restriction is opt-in configuration, not a default-deny policy nobody
 * asked for.
 */
final class IpAllowlist
{
    /**
     * The one {@see \Seviye\Core\Settings\SettingsRepositoryInterface} key
     * this feature reads/writes - defined once here so the REST controller
     * (writer) and the enforcement hook (reader) can never drift onto two
     * different key strings.
     */
    public const SETTING_KEY = 'security.admin_ip_allowlist';

    /**
     * Splits the raw newline-separated setting value into entries -
     * shared by the REST controller (serializing for display) and the
     * enforcement hook (feeding {@see isAllowed()}), so there is exactly
     * one place that decides what "one entry per line, blank lines
     * ignored" means.
     *
     * @return list<string>
     */
    public static function parseEntries(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return self::normalizeEntries((array) preg_split('/\r\n|\r|\n/', $raw));
    }

    /**
     * @param list<string> $entries
     */
    public static function isAllowed(array $entries, ?string $ip): bool
    {
        $entries = self::normalizeEntries($entries);

        if ($entries === []) {
            return true;
        }

        if ($ip === null) {
            return false;
        }

        $ipBinary = @inet_pton($ip);

        if ($ipBinary === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if (self::matches($entry, $ipBinary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $entries
     * @return list<string>
     */
    private static function normalizeEntries(array $entries): array
    {
        $trimmed = array_map('trim', $entries);

        return array_values(array_filter($trimmed, static fn (string $entry): bool => $entry !== ''));
    }

    private static function matches(string $entry, string $ipBinary): bool
    {
        if (str_contains($entry, '/')) {
            [$subnet, $rawMaskBits] = explode('/', $entry, 2);

            if (!ctype_digit($rawMaskBits)) {
                return false;
            }

            $maskBits = (int) $rawMaskBits;
        } else {
            $subnet = $entry;
            $maskBits = null;
        }

        $subnetBinary = @inet_pton($subnet);

        if ($subnetBinary === false || strlen($subnetBinary) !== strlen($ipBinary)) {
            return false;
        }

        $maskBits ??= strlen($subnetBinary) * 8;

        if ($maskBits < 0 || $maskBits > strlen($subnetBinary) * 8) {
            return false;
        }

        return self::sameNetwork($subnetBinary, $ipBinary, $maskBits);
    }

    private static function sameNetwork(string $subnetBinary, string $ipBinary, int $maskBits): bool
    {
        $fullBytes = intdiv($maskBits, 8);
        $remainderBits = $maskBits % 8;

        if ($fullBytes > 0 && substr($subnetBinary, 0, $fullBytes) !== substr($ipBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainderBits)) & 0xff;

        return (ord($subnetBinary[$fullBytes]) & $mask) === (ord($ipBinary[$fullBytes]) & $mask);
    }
}
