<?php

/**
 * Plugin Name: Seviye Students
 * Plugin URI: https://seviye.com.tr
 * Description: Student management for the Seviye Commerce Platform - student entity (branch/education year/class) and guardian (Veli) linkage.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, seviye-core, seviye-branches
 * Author: Seviye Egitim Kurumlari
 * License: Proprietary
 * Text Domain: seviye-students
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_STUDENTS_VERSION', '0.1.0');
define('SCP_STUDENTS_FILE', __FILE__);
define('SCP_STUDENTS_DIR', __DIR__);
define('SCP_STUDENTS_URL', plugin_dir_url(__FILE__));

if (!file_exists(SCP_STUDENTS_DIR . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Seviye Students is missing its Composer dependencies. Run "composer install" inside plugin/seviye-students.',
                'seviye-students'
            )
        );
    });

    return;
}

require SCP_STUDENTS_DIR . '/vendor/autoload.php';

register_activation_hook(__FILE__, [Seviye\Students\Support\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Seviye\Students\Support\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists(Seviye\Core\Plugin::class)) {
        return;
    }

    Seviye\Core\Plugin::instance()->modules()->register(new Seviye\Students\StudentsModule());
}, 10);
