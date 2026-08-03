<?php

/**
 * Bootstrap for the root-level integration test suite (`composer test:integration`,
 * `phpunit-integration.xml.dist`) - distinct from every plugin's own
 * `tests/bootstrap.php`, which stubs out WordPress entirely so the plugin's
 * OWN unit tests can run with no database (see docs/ARCHITECTURE.md, "Test
 * stratejisi"). This bootstrap instead loads the REAL WordPress core test
 * suite (`WP_UnitTestCase`, a real wpdb against a real MySQL database) - the
 * only way to verify what a single plugin's fakes/mocks structurally cannot:
 * that Core + Branches + Students + ... all actually boot together, that
 * migrations create real tables, that a REST route this platform registers
 * actually responds. Needs `wp-env` (Docker) - see tests/README.md for how
 * to run it. NOT executed by `composer lint`/any plugin's own `composer test`.
 */

declare(strict_types=1);

$_testsDir = getenv('WP_TESTS_DIR');

if ($_testsDir === false || $_testsDir === '') {
    $_testsDir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

require_once $_testsDir . '/includes/functions.php';

/**
 * Every Seviye plugin's main file, in the SAME activation order
 * theme/seviye-storefront/inc/plugin-installer.php's scp_setup_steps()
 * documents as required (dependency order, not alphabetical) - WooCommerce
 * first (every module beyond Core/Security/Branches ultimately needs it
 * active), then Core, then everything else.
 */
function scp_manually_load_plugins(): void
{
    $pluginsDir = WP_PLUGIN_DIR;

    $plugins = [
        'woocommerce/woocommerce.php',
        'seviye-core/seviye-core.php',
        'seviye-security/seviye-security.php',
        'seviye-branches/seviye-branches.php',
        'seviye-students/seviye-students.php',
        'seviye-parents/seviye-parents.php',
        'seviye-destek/seviye-destek.php',
        'seviye-pricing/seviye-pricing.php',
        'seviye-commerce/seviye-commerce.php',
        'seviye-depo/seviye-depo.php',
        'seviye-finance/seviye-finance.php',
        'seviye-reports/seviye-reports.php',
        'seviye-notifications/seviye-notifications.php',
        'seviye-api/seviye-api.php',
    ];

    foreach ($plugins as $plugin) {
        require $pluginsDir . '/' . $plugin;
    }
}

tests_add_filter('muplugins_loaded', 'scp_manually_load_plugins');

require $_testsDir . '/includes/bootstrap.php';
