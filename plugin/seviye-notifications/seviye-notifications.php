<?php

/**
 * Plugin Name: Seviye Notifications
 * Plugin URI: https://seviye.com.tr
 * Description: E-posta/SMS/panel-içi bildirim gönderimi for the Seviye Commerce Platform.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: seviye-core, seviye-branches, seviye-students, seviye-parents, seviye-finance, seviye-depo
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-notifications
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_NOTIFICATIONS_VERSION', '0.1.0');
define('SCP_NOTIFICATIONS_FILE', __FILE__);
define('SCP_NOTIFICATIONS_DIR', __DIR__);
define('SCP_NOTIFICATIONS_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_NOTIFICATIONS_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Notifications is missing its Composer dependencies. Run "composer install" inside plugin/seviye-notifications.',
                'seviye-notifications'
            )
        );
    });

    return;
}

require SCP_NOTIFICATIONS_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Notifications\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Notifications\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Notifications\NotificationsModule());
}, 10);
