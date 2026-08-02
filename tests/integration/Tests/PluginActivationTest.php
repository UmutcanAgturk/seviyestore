<?php

declare(strict_types=1);

/**
 * The first real cross-plugin check this platform has: that all 12 plugins
 * actually boot together against a real WordPress + MySQL (not each
 * plugin's own isolated unit-test fakes), and that Core's migrations
 * actually create real tables - two things no single plugin's own
 * `tests/Unit` suite can verify, since each of those stubs WordPress out
 * entirely (see docs/ARCHITECTURE.md, "Test stratejisi").
 */
final class PluginActivationTest extends WP_UnitTestCase
{
    public function test_every_seviye_plugin_is_active(): void
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugins = [
            'seviye-core/seviye-core.php',
            'seviye-security/seviye-security.php',
            'seviye-branches/seviye-branches.php',
            'seviye-students/seviye-students.php',
            'seviye-parents/seviye-parents.php',
            'seviye-pricing/seviye-pricing.php',
            'seviye-commerce/seviye-commerce.php',
            'seviye-depo/seviye-depo.php',
            'seviye-finance/seviye-finance.php',
            'seviye-reports/seviye-reports.php',
            'seviye-notifications/seviye-notifications.php',
            'seviye-api/seviye-api.php',
        ];

        foreach ($plugins as $plugin) {
            self::assertTrue(is_plugin_active($plugin), "{$plugin} eklentisi aktif olmalı.");
        }
    }

    public function test_core_container_is_available(): void
    {
        self::assertTrue(class_exists(\Seviye\Core\Plugin::class));
        self::assertInstanceOf(
            \Seviye\Core\Container\ServiceContainer::class,
            \Seviye\Core\Plugin::instance()->container()
        );
    }

    /**
     * Every module's migration ran for real (bootstrap.php's
     * scp_manually_load_plugins() calls each plugin's main file, whose
     * activation hook - or, for a plugin already "active" under wp-env,
     * its normal plugins_loaded boot - registers and runs migrations
     * through Core's MigrationRunner). scp_students in particular has a
     * real FK to scp_branches (see docs/ARCHITECTURE.md bölüm 8/9) - if
     * Branches' migration never ran, Students' migration would fail
     * outright, so this one table's existence is a reasonable proxy for
     * "the whole dependency chain came up correctly".
     */
    public function test_platform_tables_exist(): void
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'scp_branches',
            $wpdb->prefix . 'scp_students',
            $wpdb->prefix . 'scp_order_line_items',
            $wpdb->prefix . 'scp_hakedis_entries',
            $wpdb->prefix . 'scp_purchase_orders',
        ];

        foreach ($tables as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            self::assertSame($table, $found, "{$table} tablosu migration'dan sonra var olmalı.");
        }
    }
}
