<?php

declare(strict_types=1);

namespace Seviye\Pricing\Support;

/**
 * Intentionally does not remove scp_price_rules data - deactivation is
 * reversible by design, data loss is not.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
