<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Commerce intentionally does not delete scp_order_line_items data
 * when the plugin is removed - see plugin/seviye-core/uninstall.php for the
 * same reasoning platform-wide. Order data itself lives in WooCommerce's
 * own tables either way, with a single order-item meta key added by this
 * plugin.
 */
