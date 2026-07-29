<?php

declare(strict_types=1);

namespace Seviye\Pricing\Support;

use Seviye\Branches\BranchesModule;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Pricing\PricingModule;
use Seviye\Students\StudentsModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye Pricing requires Seviye Core to be installed and active.', 'seviye-pricing'));
        }

        if (!class_exists(BranchesModule::class)) {
            self::abort(__('Seviye Pricing requires Seviye Branches to be installed and active.', 'seviye-pricing'));
        }

        if (!class_exists(StudentsModule::class)) {
            self::abort(__('Seviye Pricing requires Seviye Students to be installed and active.', 'seviye-pricing'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services and registers its migrations directly:
        // on first activation, this plugin's own plugins_loaded registration
        // has not run yet within this same request - see
        // plugin/seviye-security/src/Support/Activator.php for the same
        // pattern and its full rationale. Branches and Students must already
        // be active (checked above) so their Contracts bindings this module
        // depends on are available in the shared container by the time
        // boot() runs.
        (new PricingModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_PRICING_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Pricing - Activation Error', 'seviye-pricing'),
            ['back_link' => true]
        );
    }
}
