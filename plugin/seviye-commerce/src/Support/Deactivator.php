<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

/**
 * Nothing to clean up on deactivation - Commerce keeps no scp_* tables of
 * its own in this milestone, and the WooCommerce order-item meta it writes
 * belongs to WooCommerce's own data, not this plugin's to delete.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
