<?php

declare(strict_types=1);

namespace Seviye\Depo\Support;

use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Core\Support\Environment;
use Seviye\Depo\DepoModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye Depo requires Seviye Core to be installed and active.', 'seviye-depo'));
        }

        if (!Environment::isWooCommerceActive()) {
            self::abort(__('Seviye Depo requires WooCommerce to be installed and active.', 'seviye-depo'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services, registers its migrations and REST
        // controllers directly: on first activation, this plugin's own
        // plugins_loaded registration has not run yet within this same
        // request - see plugin/seviye-security/src/Support/Activator.php
        // for the full rationale.
        (new DepoModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_DEPO_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Depo - Activation Error', 'seviye-depo'),
            ['back_link' => true]
        );
    }
}
