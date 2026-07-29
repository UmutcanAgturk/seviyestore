<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Finance intentionally does not delete scp_hakedis_entries data
 * when the plugin is removed - see plugin/seviye-core/uninstall.php for the
 * same reasoning platform-wide. This is especially true here: it is an
 * immutable, append-only financial ledger.
 */
