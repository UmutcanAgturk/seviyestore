<?php

declare(strict_types=1);

namespace Seviye\Core\Settings;

/**
 * Thin key/value store on top of scp_settings (migrated by Core since its
 * very first milestone, unused until a real consumer needed it - see
 * Seviye\Security's IP allowlist, the first caller). Values are stored and
 * returned as plain strings; a caller that needs structure (a list, JSON)
 * owns its own encode/decode, the same way HakedisSettlement's `note` or
 * PasswordTokenRecord's fields stay primitive at the repository boundary.
 */
interface SettingsRepositoryInterface
{
    /**
     * Null means "never set" - callers that want a default fall back to it
     * themselves, this repository never invents one.
     */
    public function get(string $key): ?string;

    /**
     * No null branch: a setting is either a concrete string (including "",
     * meaning "explicitly cleared") or absent (never set, get() returns
     * null). Nothing in this platform needs to distinguish "cleared" from
     * "null" for a single scalar setting value.
     */
    public function set(string $key, string $value): void;
}
