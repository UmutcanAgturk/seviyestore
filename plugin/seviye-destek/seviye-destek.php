<?php

/**
 * Plugin Name: Seviye Destek
 * Plugin URI: https://seviye.com.tr
 * Description: Veli destek/talep (helpdesk) sistemi - genel sikayet/soru bildirme, sube personelinin yanitlayip kapatabilecegi ticket akisi.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, seviye-core, seviye-branches, seviye-students
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-destek
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_DESTEK_VERSION', '0.1.0');
define('SCP_DESTEK_FILE', __FILE__);
define('SCP_DESTEK_DIR', __DIR__);
define('SCP_DESTEK_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_DESTEK_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Destek is missing its Composer dependencies. Run "composer install" inside plugin/seviye-destek.',
                'seviye-destek'
            )
        );
    });

    return;
}

require SCP_DESTEK_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Destek\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Destek\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Destek\DestekModule());
}, 10);
