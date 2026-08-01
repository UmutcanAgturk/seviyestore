<?php

/**
 * Plugin Name: Seviye Depo
 * Plugin URI: https://seviye.com.tr
 * Description: Tedarikci, satin alma siparisi ve stok hareketi takibi (Depo modulu) for the Seviye Commerce Platform.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, seviye-core
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-depo
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_DEPO_VERSION', '0.1.0');
define('SCP_DEPO_FILE', __FILE__);
define('SCP_DEPO_DIR', __DIR__);
define('SCP_DEPO_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_DEPO_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Depo is missing its Composer dependencies. Run "composer install" inside plugin/seviye-depo.',
                'seviye-depo'
            )
        );
    });

    return;
}

require SCP_DEPO_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Depo\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Depo\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Depo\DepoModule());
}, 10);
