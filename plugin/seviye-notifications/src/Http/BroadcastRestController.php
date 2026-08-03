<?php

declare(strict_types=1);

namespace Seviye\Notifications\Http;

use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Domain\ScheduledBroadcast;
use Seviye\Notifications\Domain\ScheduledBroadcastStatus;
use Seviye\Notifications\Rbac\NotificationCapability;
use Seviye\Notifications\Repository\ScheduledBroadcastRepositoryInterface;
use Seviye\Students\Contracts\BranchParentLookupInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/notifications/broadcast - "Toplu Duyuru": Genel Merkez/Bölge
 * Müdürü (SEND_BROADCAST) may address any one branch's velis or every one
 * of them platform-wide; Şube Müdürü (SEND_OWN_BRANCH_BROADCAST) only
 * their own branch's velis, regardless of what branch_id the request
 * supplies - same "server resolves scope" rule every other branch-scoped
 * write on this platform follows (see e.g.
 * Seviye\Commerce\Http\ProductsRestController).
 *
 * Recipients are resolved through BranchParentLookupInterface (published by
 * Students) - this module never depends on Students' Repository classes.
 * Each recipient gets one NotificationDispatcherInterface::dispatch() call
 * per selected channel - the same record→resolve→send→mark flow (and the
 * same honest "recorded FAILED, never silently dropped" behaviour) every
 * other notification on this platform goes through; this endpoint is a
 * fan-out over that existing pipe, not a new delivery mechanism.
 *
 * "Zamanlanmış toplu duyuru": send() accepts an optional `scheduled_at` - if
 * given (and in the future), nothing is dispatched now; a
 * Domain\ScheduledBroadcast row is persisted instead and a one-shot WP Cron
 * event registered (see Http\ScheduledBroadcastHooks, which does the actual
 * recipient resolution/dispatch when it fires). Branch scope is resolved
 * and LOCKED IN at scheduling time via the same resolveBranchScope() the
 * immediate path uses - a Şube Müdürü's own branch, or whatever an HQ
 * caller requested (null = every branch).
 */
final class BroadcastRestController extends AbstractRestController
{
    private const EVENT_NAME = 'notifications.broadcast';

    public function __construct(
        private readonly BranchParentLookupInterface $parents,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly NotificationDispatcherInterface $dispatcher,
        private readonly ScheduledBroadcastRepositoryInterface $scheduledBroadcasts
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/notifications/broadcast', [
            'methods' => 'POST',
            'callback' => [$this, 'send'],
            'permission_callback' => [$this, 'canSendBroadcast'],
            'args' => [
                'subject' => ['required' => true, 'type' => 'string'],
                'body' => ['required' => true, 'type' => 'string'],
                'branch_id' => ['required' => false, 'type' => 'integer'],
                'channels' => ['required' => false, 'type' => 'array'],
                'scheduled_at' => ['required' => false, 'type' => 'string'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/notifications/broadcast/scheduled', [
            'methods' => 'GET',
            'callback' => [$this, 'listScheduled'],
            'permission_callback' => [$this, 'canSendBroadcast'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/notifications/broadcast/scheduled/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'cancelScheduled'],
            'permission_callback' => [$this, 'canAccessScheduled'],
        ]);
    }

    public function canSendBroadcast(): bool
    {
        if (current_user_can(NotificationCapability::SEND_BROADCAST->value)) {
            return true;
        }

        if (!current_user_can(NotificationCapability::SEND_OWN_BRANCH_BROADCAST->value)) {
            return false;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id()) !== null;
    }

    public function send(WP_REST_Request $request): WP_REST_Response
    {
        $subject = trim((string) $request->get_param('subject'));
        $body = trim((string) $request->get_param('body'));

        if ($subject === '' || $body === '') {
            return new WP_REST_Response(
                ['message' => __('Başlık ve mesaj gereklidir.', 'seviye-notifications')],
                422
            );
        }

        $scheduledAt = $this->resolveScheduledAt($request);

        if ($scheduledAt === false) {
            return new WP_REST_Response(
                ['message' => __('Zamanlama tarihi gelecekte bir tarih olmalı.', 'seviye-notifications')],
                422
            );
        }

        $channels = $this->resolveChannels($request);

        if ($scheduledAt !== null) {
            $branchId = $this->resolveBranchScope($request);
            $broadcast = $this->scheduledBroadcasts->create(
                get_current_user_id(),
                $branchId,
                $subject,
                $body,
                $channels,
                $scheduledAt
            );

            wp_schedule_single_event(strtotime($scheduledAt), ScheduledBroadcastHooks::HOOK, [$broadcast->id]);

            return new WP_REST_Response($this->serializeScheduled($broadcast), 201);
        }

        $recipients = $this->resolveRecipients($request);

        foreach ($recipients as $userId) {
            foreach ($channels as $channel) {
                $this->dispatcher->dispatch($userId, $channel, self::EVENT_NAME, $subject, $body);
            }
        }

        return new WP_REST_Response(['recipient_count' => count($recipients)]);
    }

    public function listScheduled(): WP_REST_Response
    {
        $branchId = $this->resolveBranchScope(null);

        return new WP_REST_Response(array_map(
            $this->serializeScheduled(...),
            $this->scheduledBroadcasts->allForBranch($branchId)
        ));
    }

    public function cancelScheduled(WP_REST_Request $request): WP_REST_Response
    {
        $broadcast = $this->scheduledBroadcasts->find((int) $request->get_param('id'));

        if ($broadcast === null) {
            $message = __('Zamanlanmış duyuru bulunamadı.', 'seviye-notifications');

            return new WP_REST_Response(['message' => $message], 404);
        }

        if ($broadcast->status !== ScheduledBroadcastStatus::PENDING) {
            $message = __('Yalnızca bekleyen bir duyuru iptal edilebilir.', 'seviye-notifications');

            return new WP_REST_Response(['message' => $message], 422);
        }

        wp_clear_scheduled_hook(ScheduledBroadcastHooks::HOOK, [$broadcast->id]);
        $this->scheduledBroadcasts->cancel($broadcast->id);

        return new WP_REST_Response(['success' => true]);
    }

    public function canAccessScheduled(WP_REST_Request $request): bool
    {
        if (!$this->canSendBroadcast()) {
            return false;
        }

        if (current_user_can(NotificationCapability::SEND_BROADCAST->value)) {
            return true;
        }

        $broadcast = $this->scheduledBroadcasts->find((int) $request->get_param('id'));
        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        return $broadcast !== null && $broadcast->branchId === $ownBranchId;
    }

    /**
     * `null` = "send immediately" (no scheduled_at given), `false` = an
     * invalid/past scheduled_at was given (caller returns 422), a string =
     * the validated, future MySQL datetime to schedule for.
     */
    private function resolveScheduledAt(WP_REST_Request $request): string|false|null
    {
        $raw = trim((string) ($request->get_param('scheduled_at') ?? ''));

        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);

        if ($timestamp === false || $timestamp <= time()) {
            return false;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Same scope resolution resolveRecipients() uses to fetch recipients
     * NOW - reused here to LOCK IN a scheduled broadcast's branch_id at
     * creation time, and to scope listScheduled()'s visibility. $request
     * is null for listScheduled(), which has no branch_id param to read
     * (an HQ caller always sees every branch's scheduled broadcasts, never
     * just one) - only the write path (send()) lets HQ target one branch.
     */
    private function resolveBranchScope(?WP_REST_Request $request): ?int
    {
        if (current_user_can(NotificationCapability::SEND_BROADCAST->value)) {
            return $request !== null ? $this->intParam($request, 'branch_id') : null;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    /**
     * @return list<int>
     */
    private function resolveRecipients(WP_REST_Request $request): array
    {
        if (current_user_can(NotificationCapability::SEND_BROADCAST->value)) {
            $branchId = $this->intParam($request, 'branch_id');

            return $branchId !== null
                ? $this->parents->parentUserIdsForBranch($branchId)
                : $this->parents->allParentUserIds();
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        return $ownBranchId !== null ? $this->parents->parentUserIdsForBranch($ownBranchId) : [];
    }

    /**
     * @return list<NotificationChannel>
     */
    private function resolveChannels(WP_REST_Request $request): array
    {
        $requested = $request->get_param('channels');
        $default = [NotificationChannel::EMAIL, NotificationChannel::PANEL];

        if (!is_array($requested) || $requested === []) {
            return $default;
        }

        $channels = array_values(array_filter(array_map(
            static fn ($value): ?NotificationChannel => NotificationChannel::tryFrom((string) $value),
            $requested
        )));

        return $channels === [] ? $default : $channels;
    }

    private function intParam(WP_REST_Request $request, string $name): ?int
    {
        $value = $request->get_param($name);

        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeScheduled(ScheduledBroadcast $broadcast): array
    {
        $channelValues = array_map(
            static fn (NotificationChannel $channel): string => $channel->value,
            $broadcast->channels
        );

        return [
            'id' => $broadcast->id,
            'branch_id' => $broadcast->branchId,
            'subject' => $broadcast->subject,
            'body' => $broadcast->body,
            'channels' => $channelValues,
            'scheduled_at' => $broadcast->scheduledAt,
            'status' => $broadcast->status->value,
            'created_at' => $broadcast->createdAt,
        ];
    }
}
