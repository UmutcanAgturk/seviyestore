<?php

/**
 * Plugin Name: Seviye Reports
 * Plugin URI: https://seviye.com.tr
 * Description: Şube/ürün/kategori/dönem bazlı satış raporlama (CSV/Excel) for the Seviye Commerce Platform.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, seviye-core, seviye-branches, seviye-students, seviye-commerce
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-reports
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_REPORTS_VERSION', '0.1.0');
define('SCP_REPORTS_FILE', __FILE__);
define('SCP_REPORTS_DIR', __DIR__);
define('SCP_REPORTS_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_REPORTS_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Reports is missing its Composer dependencies. Run "composer install" inside plugin/seviye-reports.',
                'seviye-reports'
            )
        );
    });

    return;
}

require SCP_REPORTS_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Reports\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Reports\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Reports\ReportsModule());
}, 10);
