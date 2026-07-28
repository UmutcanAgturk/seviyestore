<?php

declare(strict_types=1);

namespace Seviye\Core\Support;

use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Core\Rbac\RoleRegistrar;

final class Activator
{
    public static function activate(): void
    {
        if (!Environment::satisfiesPhpVersion()) {
            self::abort(sprintf(
                /* translators: 1: required PHP version, 2: current PHP version */
                __('Seviye Core requires PHP %1$s or higher. The current version is %2$s.', 'seviye-core'),
                Environment::MIN_PHP_VERSION,
                PHP_VERSION
            ));
        }

        if (!Environment::isWooCommerceActive()) {
            self::abort(__('Seviye Core requires WooCommerce to be installed and active.', 'seviye-core'));
        }

        $container = Plugin::instance()->container();
        $container->get(RoleRegistrar::class)->register();
        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_CORE_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Core - Activation Error', 'seviye-core'),
            ['back_link' => true]
        );
    }
}
