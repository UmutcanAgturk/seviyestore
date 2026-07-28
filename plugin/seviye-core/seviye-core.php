<?php

/**
 * Plugin Name: Seviye Core
 * Plugin URI: https://seviye.com.tr
 * Description: Core framework for the Seviye Commerce Platform - DI container, event bus, RBAC, migrations, audit logging and shared infrastructure for every Seviye module. Not a standalone feature plugin.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-core
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_CORE_VERSION', '0.1.0');
define('SCP_CORE_FILE', __FILE__);
define('SCP_CORE_DIR', __DIR__);
define('SCP_CORE_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_CORE_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Core is missing its Composer dependencies. Run "composer install" inside plugin/seviye-core.',
                'seviye-core'
            )
        );
    });

    return;
}

require SCP_CORE_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Core\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Core\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    Seviye\Core\Plugin::instance()->boot();
}, 0);
