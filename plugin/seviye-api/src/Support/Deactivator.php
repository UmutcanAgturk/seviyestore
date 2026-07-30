<?php

declare(strict_types=1);

namespace Seviye\Api\Support;

/**
 * Intentionally does not remove scp_api_keys data - deactivation is
 * reversible by design, data loss is not (same reasoning as every other
 * module's Deactivator).
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
