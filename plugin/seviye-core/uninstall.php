<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Seviye Core intentionally does not delete any scp_* data (branches,
 * students, parents, orders, finance records...) when the plugin is removed.
 * This is a commercial, multi-branch education platform - losing that data as
 * a side effect of a plugin removal click would be an unrecoverable,
 * high-blast-radius mistake. Data purges are a deliberate, explicit operation
 * performed via the HQ admin panel or WP-CLI, never implied by uninstall.
 */
