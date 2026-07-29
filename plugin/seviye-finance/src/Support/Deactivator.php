<?php

declare(strict_types=1);

namespace Seviye\Finance\Support;

/**
 * Intentionally does not remove scp_hakedis_entries data - deactivation is
 * reversible by design, data loss is not (same reasoning as every other
 * module's Deactivator, doubly true for an immutable financial ledger).
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
