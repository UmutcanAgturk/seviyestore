<?php

declare(strict_types=1);

namespace Seviye\Depo\Support;

/**
 * Intentionally does not remove any Depo data - deactivation is reversible
 * by design, data loss is not (same reasoning as every other module's
 * Deactivator, doubly true for a purchase-order/stock-movement history).
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
