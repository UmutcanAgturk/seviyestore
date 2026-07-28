<?php

declare(strict_types=1);

namespace Seviye\Security\Support;

use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Security\SecurityModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye Security requires Seviye Core to be installed and active.', 'seviye-security'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services and registers its migrations directly:
        // on first activation, this plugin's own plugins_loaded registration
        // has not run yet within this same request, so the module cannot be
        // relied on to have already booted itself into the shared container.
        (new SecurityModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_SECURITY_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Security - Activation Error', 'seviye-security'),
            ['back_link' => true]
        );
    }
}
