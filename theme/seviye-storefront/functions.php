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

require SCP_THEME_DIR . '/inc/setup.php';
require SCP_THEME_DIR . '/inc/plugin-installer.php';
require SCP_THEME_DIR . '/inc/access-gate.php';
require SCP_THEME_DIR . '/inc/zones.php';
require SCP_THEME_DIR . '/inc/ip-restriction.php';
require SCP_THEME_DIR . '/inc/woocommerce.php';
require SCP_THEME_DIR . '/inc/assets.php';
