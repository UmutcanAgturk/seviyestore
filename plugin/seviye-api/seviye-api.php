<?php

/**
 * Plugin Name: Seviye API
 * Plugin URI: https://seviye.com.tr
 * Description: seviye/v1 REST namespace için API anahtarı tabanlı kimlik doğrulama (ERP/CRM/muhasebe/mobil entegrasyonu).
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: seviye-core
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-api
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_API_VERSION', '0.1.0');
define('SCP_API_FILE', __FILE__);
define('SCP_API_DIR', __DIR__);
define('SCP_API_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_API_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye API is missing its Composer dependencies. Run "composer install" inside plugin/seviye-api.',
                'seviye-api'
            )
        );
    });

    return;
}

require SCP_API_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Api\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Api\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Api\ApiModule());
}, 10);
