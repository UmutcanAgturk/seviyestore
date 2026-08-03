<?php

declare(strict_types=1);

namespace Seviye\Destek\Support;

/**
 * Intentionally does not remove scp_support_tickets/scp_support_messages
 * data - deactivation is reversible by design, data loss is not.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
