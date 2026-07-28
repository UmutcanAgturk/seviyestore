<?php

/**
 * Plugin Name: Seviye Branches
 * Plugin URI: https://seviye.com.tr
 * Description: Branch (şube) management for the Seviye Commerce Platform - branch entity, IBAN/commission, and staff ("Yetkililer") membership.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, seviye-core
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-branches
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_BRANCHES_VERSION', '0.1.0');
define('SCP_BRANCHES_FILE', __FILE__);
define('SCP_BRANCHES_DIR', __DIR__);
define('SCP_BRANCHES_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_BRANCHES_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Branches is missing its Composer dependencies. Run "composer install" inside plugin/seviye-branches.',
                'seviye-branches'
            )
        );
    });

    return;
}

require SCP_BRANCHES_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Branches\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Branches\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Branches\BranchesModule());
}, 10);
