<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

use Seviye\Commerce\CommerceModule;
use Seviye\Core\Plugin;
use Seviye\Core\Support\Environment;
use Seviye\Pricing\PricingModule;
use Seviye\Students\StudentsModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye Commerce requires Seviye Core to be installed and active.', 'seviye-commerce'));
        }

        if (!Environment::isWooCommerceActive()) {
            self::abort(__('Seviye Commerce requires WooCommerce to be installed and active.', 'seviye-commerce'));
        }

        if (!class_exists(StudentsModule::class)) {
            self::abort(__('Seviye Commerce requires Seviye Students to be installed and active.', 'seviye-commerce'));
        }

        if (!class_exists(PricingModule::class)) {
            self::abort(__('Seviye Commerce requires Seviye Pricing to be installed and active.', 'seviye-commerce'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services directly: on first activation, this
        // plugin's own plugins_loaded registration has not run yet within
        // this same request - see
        // plugin/seviye-security/src/Support/Activator.php for the same
        // pattern and its full rationale. Students and Pricing must already
        // be active (checked above) so their Contracts bindings this module
        // depends on are available in the shared container by the time
        // boot() runs. Unlike every other module so far, there are no
        // migrations to run - Commerce keeps no scp_* tables of its own in
        // this milestone.
        (new CommerceModule())->boot($container);

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_COMMERCE_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Commerce - Activation Error', 'seviye-commerce'),
            ['back_link' => true]
        );
    }
}
