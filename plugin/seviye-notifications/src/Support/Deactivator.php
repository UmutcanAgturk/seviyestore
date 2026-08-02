<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

use Seviye\Notifications\Http\WeeklyDigestHooks;

/**
 * Intentionally does not remove scp_notifications data - deactivation is
 * reversible by design, data loss is not (same reasoning as every other
 * module's Deactivator). The scheduled weekly digest cron event IS cleared
 * though - unlike stored data, a dangling wp_schedule_event() survives
 * deactivation on its own and would keep firing (and failing, since the
 * container it needs is gone) if left alone.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(WeeklyDigestHooks::HOOK);

        flush_rewrite_rules();
    }
}
