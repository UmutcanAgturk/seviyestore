<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Commerce keeps no scp_* tables of its own in this milestone (order
 * data lives in WooCommerce's own tables, with a single order-item meta key
 * added by this plugin) - see plugin/seviye-core/uninstall.php for the
 * platform-wide reasoning against destructive uninstall behaviour in
 * general.
 */
