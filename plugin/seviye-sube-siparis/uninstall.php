<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Şube Siparişleri intentionally does not delete its tables' data
 * when the plugin is removed - see plugin/seviye-core/uninstall.php for the
 * same reasoning platform-wide. Especially true here: scp_branch_orders and
 * scp_branch_order_items are the only record of which branch received a
 * free-quota product and which order was actually paid for.
 */
