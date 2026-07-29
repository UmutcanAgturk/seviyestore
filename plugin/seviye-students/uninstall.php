<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Students intentionally does not delete scp_students or
 * scp_student_parents data when the plugin is removed - see
 * plugin/seviye-core/uninstall.php for the same reasoning platform-wide.
 */
