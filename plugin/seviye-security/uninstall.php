<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Security intentionally does not delete scp_user_identities or
 * scp_password_tokens data when the plugin is removed - see
 * plugin/seviye-core/uninstall.php for the same reasoning platform-wide.
 */
