<?php

declare(strict_types=1);

namespace Seviye\Notifications\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Notifications\Domain\Notification;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Repository\NotificationRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/notifications/mine/* - self-service, authenticated only
 * (is_user_logged_in(), no RBAC capability), exactly mirroring
 * Seviye\Security\Http\TwoFactorRestController: every role reads its own
 * PANEL-channel notifications, this is not a permission-scoped resource.
 * markRead() is scoped to the current user at the repository layer, not
 * merely by convention here - the same "server resolves scope, never
 * trusts the client" rule the rest of the platform applies to writes.
 */
final class NotificationsRestController extends AbstractRestController
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/notifications/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'mine'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/notifications/mine/unread-count', [
            'methods' => 'GET',
            'callback' => [$this, 'unreadCount'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/notifications/mine/(?P<id>\d+)/read', [
            'methods' => 'POST',
            'callback' => [$this, 'markRead'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);
    }

    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function mine(): WP_REST_Response
    {
        $notifications = $this->notifications->findForUser(get_current_user_id(), NotificationChannel::PANEL);

        return new WP_REST_Response(array_map(
            static fn (Notification $n): array => [
                'id' => $n->id,
                'event_name' => $n->eventName,
                'subject' => $n->subject,
                'body' => $n->body,
                'created_at' => $n->createdAt,
                'read_at' => $n->readAt,
            ],
            $notifications
        ));
    }

    public function unreadCount(): WP_REST_Response
    {
        return new WP_REST_Response([
            'unread_count' => $this->notifications->unreadCountForUser(get_current_user_id()),
        ]);
    }

    public function markRead(WP_REST_Request $request): WP_REST_Response
    {
        $this->notifications->markRead((int) $request->get_param('id'), get_current_user_id());

        return new WP_REST_Response(['success' => true]);
    }
}
