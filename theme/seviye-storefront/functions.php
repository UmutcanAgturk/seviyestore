<?php

/**
 * Seviye Storefront bootstrap.
 *
 * Kept deliberately procedural (WordPress theme convention, not a PSR-4
 * plugin domain): this file wires small, single-purpose includes together
 * rather than containing business logic itself. The actual identity/auth
 * decisions (is this login valid, which role goes where) live in the
 * Seviye Security plugin, which this theme only calls into.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_THEME_VERSION', '0.1.0');
define('SCP_THEME_DIR', get_template_directory());
define('SCP_THEME_URL', get_template_directory_uri());

/**
 * Per-asset cache-busting version. SCP_THEME_VERSION alone is a static
 * string that has never changed since the theme's first commit - every
 * enqueue call using it verbatim produces the SAME versioned URL
 * (.../students-panel.js?ver=0.1.0) across every delivered update, so
 * browsers (and any page-caching layer) have no signal to fetch the new
 * file after a theme/plugin re-upload. This derives the version from the
 * asset file's own mtime instead, so it changes automatically whenever the
 * file's contents change - no manual bump required, and it can never go
 * stale the way a hardcoded constant can.
 */
function scp_asset_version(string $relativePath): string
{
    $file = SCP_THEME_DIR . $relativePath;

    return is_file($file) ? (string) filemtime($file) : SCP_THEME_VERSION;
}

require SCP_THEME_DIR . '/inc/setup.php';
require SCP_THEME_DIR . '/inc/plugin-installer.php';
require SCP_THEME_DIR . '/inc/access-gate.php';
require SCP_THEME_DIR . '/inc/zones.php';
require SCP_THEME_DIR . '/inc/ip-restriction.php';
require SCP_THEME_DIR . '/inc/woocommerce.php';
require SCP_THEME_DIR . '/inc/assets.php';
require SCP_THEME_DIR . '/inc/branding.php';
require SCP_THEME_DIR . '/inc/pwa.php';
require SCP_THEME_DIR . '/templates/partials/icon.php';
