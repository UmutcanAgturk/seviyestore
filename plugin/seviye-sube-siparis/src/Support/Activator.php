<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Support;

use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Core\Support\Environment;
use Seviye\SubeSiparis\SubeSiparisModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__(
                'Seviye Şube Siparişleri requires Seviye Core to be installed and active.',
                'seviye-sube-siparis'
            ));
        }

        if (!Environment::isWooCommerceActive()) {
            self::abort(__(
                'Seviye Şube Siparişleri requires WooCommerce to be installed and active.',
                'seviye-sube-siparis'
            ));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services, registers its migrations directly:
        // on first activation, this plugin's own plugins_loaded registration
        // has not run yet within this same request - see
        // plugin/seviye-depo/src/Support/Activator.php for the same pattern.
        (new SubeSiparisModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_SUBE_SIPARIS_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Şube Siparişleri - Activation Error', 'seviye-sube-siparis'),
            ['back_link' => true]
        );
    }
}
