<?php

declare(strict_types=1);

namespace Seviye\Finance\Support;

use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Finance\FinanceModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye Finance requires Seviye Core to be installed and active.', 'seviye-finance'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services, registers its migration, and wires
        // its EventBus listeners directly: on first activation, this
        // plugin's own plugins_loaded registration has not run yet within
        // this same request - see
        // plugin/seviye-security/src/Support/Activator.php for the full
        // rationale. Unlike Students/Pricing/Commerce, there is no other
        // module's Contracts binding to wait for here - Finance only needs
        // Core's EventBus to be ready, which it always is.
        (new FinanceModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_FINANCE_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Finance - Activation Error', 'seviye-finance'),
            ['back_link' => true]
        );
    }
}
