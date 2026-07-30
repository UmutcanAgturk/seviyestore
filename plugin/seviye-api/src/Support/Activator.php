<?php

declare(strict_types=1);

namespace Seviye\Api\Support;

use Seviye\Api\ApiModule;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye API requires Seviye Core to be installed and active.', 'seviye-api'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services, registers its migration, and wires
        // the rest_authentication_errors filter directly: on first
        // activation, this plugin's own plugins_loaded registration has not
        // run yet within this same request - see
        // plugin/seviye-security/src/Support/Activator.php for the full
        // rationale.
        (new ApiModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_API_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye API - Activation Error', 'seviye-api'),
            ['back_link' => true]
        );
    }
}
