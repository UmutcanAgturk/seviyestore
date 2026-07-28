<?php

declare(strict_types=1);

namespace Seviye\Branches\Support;

use Seviye\Branches\BranchesModule;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye Branches requires Seviye Core to be installed and active.', 'seviye-branches'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services and registers its migrations directly:
        // on first activation, this plugin's own plugins_loaded registration
        // has not run yet within this same request - see
        // plugin/seviye-security/src/Support/Activator.php for the same
        // pattern and its full rationale.
        (new BranchesModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_BRANCHES_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Branches - Activation Error', 'seviye-branches'),
            ['back_link' => true]
        );
    }
}
