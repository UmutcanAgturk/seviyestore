<?php

/**
 * Plugin Name: Seviye Pricing
 * Plugin URI: https://seviye.com.tr
 * Description: Custom pricing engine for the Seviye Commerce Platform - student/branch/general price rules with priority-based resolution.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, seviye-core, seviye-branches, seviye-students
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-pricing
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_PRICING_VERSION', '0.1.0');
define('SCP_PRICING_FILE', __FILE__);
define('SCP_PRICING_DIR', __DIR__);
define('SCP_PRICING_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_PRICING_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Pricing is missing its Composer dependencies. Run "composer install" inside plugin/seviye-pricing.',
                'seviye-pricing'
            )
        );
    });

    return;
}

require SCP_PRICING_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Pricing\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Pricing\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Pricing\PricingModule());
}, 10);
