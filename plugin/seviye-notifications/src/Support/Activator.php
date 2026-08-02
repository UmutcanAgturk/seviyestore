<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Depo\DepoModule;
use Seviye\Finance\FinanceModule;
use Seviye\Notifications\NotificationsModule;
use Seviye\Parents\ParentsModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__(
                'Seviye Notifications requires Seviye Core to be installed and active.',
                'seviye-notifications'
            ));
        }

        // A real Contracts dependency: the SMS channel resolves a veli's
        // phone number exclusively through Parents' published
        // Contracts\ParentContactLookupInterface, never its internal tables.
        if (!class_exists(ParentsModule::class)) {
            self::abort(__(
                'Seviye Notifications requires Seviye Parents to be installed and active.',
                'seviye-notifications'
            ));
        }

        // Real Contracts dependencies too - WeeklyDigestHooks reads
        // Finance's Contracts\HakedisTotalsInterface and Depo's
        // Contracts\PurchaseSuggestionSummaryInterface.
        if (!class_exists(FinanceModule::class)) {
            self::abort(__(
                'Seviye Notifications requires Seviye Finance to be installed and active.',
                'seviye-notifications'
            ));
        }

        if (!class_exists(DepoModule::class)) {
            self::abort(__(
                'Seviye Notifications requires Seviye Depo to be installed and active.',
                'seviye-notifications'
            ));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services, registers its migration, and wires
        // its EventBus listeners directly: on first activation, this
        // plugin's own plugins_loaded registration has not run yet within
        // this same request - see plugin/seviye-security/src/Support/Activator.php
        // for the full rationale.
        (new NotificationsModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_NOTIFICATIONS_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Notifications - Activation Error', 'seviye-notifications'),
            ['back_link' => true]
        );
    }
}
