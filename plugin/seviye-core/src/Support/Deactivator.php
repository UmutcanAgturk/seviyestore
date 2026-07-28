<?php

declare(strict_types=1);

namespace Seviye\Core\Support;

/**
 * Intentionally does not remove roles, capabilities, or scp_* tables:
 * deactivation is reversible by design, data loss is not. Destructive
 * cleanup is reserved for a deliberate, explicit operation - never a side
 * effect of clicking "Deactivate".
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
