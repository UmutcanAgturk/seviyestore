<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Nothing to clean up - Seviye Reports owns no scp_* table of its own, it
 * only reads other modules' data through their published Contracts.
 */
