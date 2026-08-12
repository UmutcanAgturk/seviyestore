<?php

/**
 * Plugin Name: Seviye Şube Siparişleri
 * Plugin URI: https://seviye.com.tr
 * Description: Şubelerin ürün bazlı ücretsiz kotasını (Genel Merkez'in belirlediği) kullanarak, kota aşımını Genel Merkez onayı sonrası WooCommerce üzerinden kart ile ödeyerek sipariş açtığı Şube Siparişleri modülü.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, seviye-core, seviye-branches
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-sube-siparis
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_SUBE_SIPARIS_VERSION', '0.1.0');
define('SCP_SUBE_SIPARIS_FILE', __FILE__);
define('SCP_SUBE_SIPARIS_DIR', __DIR__);
define('SCP_SUBE_SIPARIS_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_SUBE_SIPARIS_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Şube Siparişleri is missing its Composer dependencies. Run "composer install" inside plugin/seviye-sube-siparis.',
                'seviye-sube-siparis'
            )
        );
    });

    return;
}

require SCP_SUBE_SIPARIS_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\SubeSiparis\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\SubeSiparis\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\SubeSiparis\SubeSiparisModule());
}, 10);
