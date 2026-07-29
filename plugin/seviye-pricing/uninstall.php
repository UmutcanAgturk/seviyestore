<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Pricing intentionally does not delete scp_price_rules data when
 * the plugin is removed - see plugin/seviye-core/uninstall.php for the same
 * reasoning platform-wide.
 */
