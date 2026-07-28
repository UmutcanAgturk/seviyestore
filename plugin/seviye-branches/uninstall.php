<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Branches intentionally does not delete scp_branches or
 * scp_branch_users data when the plugin is removed - see
 * plugin/seviye-core/uninstall.php for the same reasoning platform-wide.
 */
