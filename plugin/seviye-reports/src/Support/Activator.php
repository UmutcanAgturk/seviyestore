<?php

declare(strict_types=1);

namespace Seviye\Reports\Support;

use Seviye\Branches\BranchesModule;
use Seviye\Commerce\CommerceModule;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Core\Support\Environment;
use Seviye\Reports\ReportsModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye Reports requires Seviye Core to be installed and active.', 'seviye-reports'));
        }

        if (!Environment::isWooCommerceActive()) {
            self::abort(__('Seviye Reports requires WooCommerce to be installed and active.', 'seviye-reports'));
        }

        if (!class_exists(BranchesModule::class)) {
            self::abort(__('Seviye Reports requires Seviye Branches to be installed and active.', 'seviye-reports'));
        }

        // A real Contracts dependency: the sales report reads order line
        // item history exclusively through Commerce's published
        // Contracts\OrderLineItemQueryInterface, never its internal tables.
        if (!class_exists(CommerceModule::class)) {
            self::abort(__('Seviye Reports requires Seviye Commerce to be installed and active.', 'seviye-reports'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services and registers its REST controller
        // directly: on first activation, this plugin's own plugins_loaded
        // registration has not run yet within this same request - see
        // plugin/seviye-security/src/Support/Activator.php for the full
        // rationale. Branches/Commerce must already be active (checked
        // above) so their Contracts bindings this module depends on are
        // available in the shared container by the time boot() runs.
        (new ReportsModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_REPORTS_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Reports - Activation Error', 'seviye-reports'),
            ['back_link' => true]
        );
    }
}
