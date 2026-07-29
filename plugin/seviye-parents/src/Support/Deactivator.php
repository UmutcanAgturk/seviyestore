<?php

declare(strict_types=1);

namespace Seviye\Parents\Support;

/**
 * Intentionally does not remove scp_parent_profiles data - deactivation is
 * reversible by design, data loss is not.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
