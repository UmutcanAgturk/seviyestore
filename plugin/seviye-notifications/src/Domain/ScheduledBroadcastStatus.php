<?php

declare(strict_types=1);

namespace Seviye\Notifications\Domain;

enum ScheduledBroadcastStatus: string
{
    /** Waiting for its scheduled_at time - WP Cron (Http\ScheduledBroadcastHooks) has not fired yet. */
    case PENDING = 'pending';

    /** WP Cron fired and every recipient/channel dispatch was attempted. */
    case SENT = 'sent';

    /** Cancelled before it fired - see Http\BroadcastRestController::cancelScheduled(). */
    case CANCELLED = 'cancelled';
}
