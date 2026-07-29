<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Parents intentionally does not delete scp_parent_profiles data
 * when the plugin is removed - see plugin/seviye-core/uninstall.php for
 * the same reasoning platform-wide.
 */
