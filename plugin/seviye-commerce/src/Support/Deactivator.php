<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

/**
 * Intentionally does not remove scp_order_line_items data - deactivation is
 * reversible by design, data loss is not (same reasoning as every other
 * module's Deactivator). The WooCommerce order-item meta this plugin writes
 * belongs to WooCommerce's own data either way, not this plugin's to delete.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
