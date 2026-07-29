<?php

declare(strict_types=1);

namespace Seviye\Finance\Support;

use Seviye\Branches\BranchesModule;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Plugin;
use Seviye\Finance\FinanceModule;
use Seviye\Students\StudentsModule;

final class Activator
{
    public static function activate(): void
    {
        if (!class_exists(Plugin::class)) {
            self::abort(__('Seviye Finance requires Seviye Core to be installed and active.', 'seviye-finance'));
        }

        if (!class_exists(BranchesModule::class)) {
            self::abort(__('Seviye Finance requires Seviye Branches to be installed and active.', 'seviye-finance'));
        }

        // Not a Contracts dependency (Finance never calls into Students'
        // PHP code), but scp_hakedis_entries.student_id's FK still needs
        // scp_students to already exist for CreateHakedisEntriesTable's
        // ForeignKeyInstaller::ensure() call to succeed.
        if (!class_exists(StudentsModule::class)) {
            self::abort(__('Seviye Finance requires Seviye Students to be installed and active.', 'seviye-finance'));
        }

        $container = Plugin::instance()->container();

        // Binds this module's services, registers its migration, wires its
        // EventBus listeners, and registers its REST controller directly:
        // on first activation, this plugin's own plugins_loaded
        // registration has not run yet within this same request - see
        // plugin/seviye-security/src/Support/Activator.php for the full
        // rationale. Branches must already be active (checked above) so
        // its Contracts bindings the REST layer depends on are available
        // in the shared container by the time boot() runs.
        (new FinanceModule())->boot($container);

        $container->get(MigrationRunner::class)->run();

        flush_rewrite_rules();
    }

    private static function abort(string $message): void
    {
        deactivate_plugins(plugin_basename(SCP_FINANCE_FILE));

        wp_die(
            esc_html($message),
            esc_html__('Seviye Finance - Activation Error', 'seviye-finance'),
            ['back_link' => true]
        );
    }
}
