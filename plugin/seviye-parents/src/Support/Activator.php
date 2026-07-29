<?php

declare(strict_types=1);

namespace Seviye\Parents\Support;

use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Parents\ParentsModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye Parents requires Seviye Core to be installed and active.', 'seviye-parents'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services and registers its migrations directly:
        // on first activation, this plugin's own plugins_loaded registration
        // has not run yet within this same request - see
        // plugin/seviye-security/src/Support/Activator.php for the full
        // rationale.
        (new ParentsModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_PARENTS_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Parents - Activation Error', 'seviye-parents'),
            ['back_link' => true]
        );
    }
}
