<?php

declare(strict_types=1);

namespace Seviye\Notifications\Http;

use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Rbac\NotificationCapability;
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
 */
final class BroadcastRestController extends AbstractRestController
{
    private const EVENT_NAME = 'notifications.broadcast';

    public function __construct(
        private readonly BranchParentLookupInterface $parents,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly NotificationDispatcherInterface $dispatcher
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
            ],
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

        $recipients = $this->resolveRecipients($request);
        $channels = $this->resolveChannels($request);

        foreach ($recipients as $userId) {
            foreach ($channels as $channel) {
                $this->dispatcher->dispatch($userId, $channel, self::EVENT_NAME, $subject, $body);
            }
        }

        return new WP_REST_Response(['recipient_count' => count($recipients)]);
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
}
