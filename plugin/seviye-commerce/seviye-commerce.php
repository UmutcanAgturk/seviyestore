<?php

/**
 * Plugin Name: Seviye Commerce
 * Plugin URI: https://seviye.com.tr
 * Description: WooCommerce integration for the Seviye Commerce Platform - student-scoped cart pricing, order flow, split payment.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, seviye-core, seviye-branches, seviye-students, seviye-pricing
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-commerce
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_COMMERCE_VERSION', '0.1.0');
define('SCP_COMMERCE_FILE', __FILE__);
define('SCP_COMMERCE_DIR', __DIR__);
define('SCP_COMMERCE_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_COMMERCE_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Commerce is missing its Composer dependencies. Run "composer install" inside plugin/seviye-commerce.',
                'seviye-commerce'
            )
        );
    });

    return;
}

require SCP_COMMERCE_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Commerce\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Commerce\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Commerce\CommerceModule());
}, 10);
