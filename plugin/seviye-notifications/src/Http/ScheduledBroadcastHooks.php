<?php

declare(strict_types=1);

namespace Seviye\Notifications\Http;

use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\ScheduledBroadcastStatus;
use Seviye\Notifications\Repository\ScheduledBroadcastRepositoryInterface;
use Seviye\Students\Contracts\BranchParentLookupInterface;

/**
 * "Zamanlanmış toplu duyuru" - fires ONE `wp_schedule_single_event()` per
 * scheduled broadcast (registered at creation time by
 * BroadcastRestController::send()), unlike WeeklyDigestHooks' recurring
 * `wp_schedule_event()`. Recipients are resolved HERE, at send time, not
 * frozen at creation time - a veli linked to the branch between scheduling
 * and firing is still included, the same "always current" semantics the
 * immediate-send path already has via BranchParentLookupInterface.
 */
final class ScheduledBroadcastHooks
{
    public const HOOK = 'scp_notifications_scheduled_broadcast';

    public function __construct(
        private readonly ScheduledBroadcastRepositoryInterface $scheduled,
        private readonly BranchParentLookupInterface $parents,
        private readonly NotificationDispatcherInterface $dispatcher
    ) {
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'send'], 10, 1);
    }

    /**
     * Guards against a cancelled-in-the-meantime broadcast still firing
     * (BroadcastRestController::cancelScheduled() clears the scheduled
     * event, but a race between that and an already-in-flight cron trigger
     * is cheap to guard against here too) and against a re-fired event
     * (WP Cron is "at least once", not "exactly once") re-sending.
     */
    public function send(int $id): void
    {
        $broadcast = $this->scheduled->find($id);

        if ($broadcast === null || $broadcast->status !== ScheduledBroadcastStatus::PENDING) {
            return;
        }

        $recipients = $broadcast->branchId !== null
            ? $this->parents->parentUserIdsForBranch($broadcast->branchId)
            : $this->parents->allParentUserIds();

        foreach ($recipients as $userId) {
            foreach ($broadcast->channels as $channel) {
                $this->dispatcher->dispatch($userId, $channel, self::HOOK, $broadcast->subject, $broadcast->body);
            }
        }

        $this->scheduled->markSent($id);
    }
}
