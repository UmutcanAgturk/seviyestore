<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

/**
 * "Beden Rehberi" - a parent-facing yaş/boy → beden lookup table, HQ-edited
 * (bkz. Http\SizeGuideRestController, ProductCapability::MANAGE_SIZE_GUIDE).
 * Stored as ONE JSON-encoded {@see \Seviye\Core\Settings\SettingsRepositoryInterface}
 * value rather than a new database table - the same "structure is the
 * caller's own encode/decode" reasoning Security's IpAllowlist already
 * uses for the /admin IP allowlist (bkz. o sınıfın docblock'u), just JSON
 * instead of newline-separated strings since a row here has several
 * fields, not one. The REST controller (writer) and the theme's product
 * page render hook (reader, via the `scp_commerce_size_guide_rows` filter
 * köprüsü - bkz. CommerceModule::boot()) both go through this one class so
 * neither can drift onto a different JSON shape.
 */
final class SizeGuide
{
    public const SETTING_KEY = 'commerce.size_guide';

    /**
     * @return list<SizeGuideRow>
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !array_is_list($decoded)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $row): SizeGuideRow => SizeGuideRow::fromArray(is_array($row) ? $row : []),
            $decoded
        ));
    }

    /**
     * @param list<SizeGuideRow> $rows
     */
    public static function serialize(array $rows): string
    {
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode(array_map(static fn (SizeGuideRow $row): array => $row->toArray(), $rows))
            : json_encode(array_map(static fn (SizeGuideRow $row): array => $row->toArray(), $rows));

        return $encoded !== false ? $encoded : '[]';
    }
}
