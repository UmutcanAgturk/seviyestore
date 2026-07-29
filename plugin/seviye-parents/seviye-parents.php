<?php

/**
 * Plugin Name: Seviye Parents
 * Plugin URI: https://seviye.com.tr
 * Description: Veli (guardian) profile management for the Seviye Commerce Platform - phone, notification preference, KVKK consent.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, seviye-core
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-parents
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_PARENTS_VERSION', '0.1.0');
define('SCP_PARENTS_FILE', __FILE__);
define('SCP_PARENTS_DIR', __DIR__);
define('SCP_PARENTS_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_PARENTS_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Parents is missing its Composer dependencies. Run "composer install" inside plugin/seviye-parents.',
                'seviye-parents'
            )
        );
    });

    return;
}

require SCP_PARENTS_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Parents\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Parents\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Parents\ParentsModule());
}, 10);
