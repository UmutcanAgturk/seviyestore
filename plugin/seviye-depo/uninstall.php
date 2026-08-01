<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Depo intentionally does not delete its tables' data when the
 * plugin is removed - see plugin/seviye-core/uninstall.php for the same
 * reasoning platform-wide. This is especially true for
 * scp_stock_movements: an append-only audit ledger.
 */
