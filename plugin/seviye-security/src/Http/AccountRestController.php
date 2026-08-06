<?php

declare(strict_types=1);

namespace Seviye\Security\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Security\Auth\CredentialGatewayInterface;
use Seviye\Security\Auth\MustChangePasswordGatewayInterface;
use Seviye\Security\Auth\PasswordPolicy;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/security/password - self-service password change while already
 * logged in ("Profilim bölümünde ... Şifre değiştirme de olsun"), distinct
 * from AuthRestController's token-based forgot-password/first-setup flow
 * (which has no active session to preserve). Not gated by any capability -
 * like TwoFactorRestController, every role manages its own account this
 * way, not a permission-scoped resource.
 *
 * Requires the CURRENT password again before accepting a new one - same
 * "prove you're still you" bar TwoFactorRestController::disable() sets for
 * a security-sensitive self-service action.
 *
 * wp_set_password() destroys every session token for the target user
 * (see Http\Admin\UserListPage's identical comment) - including the one
 * the CURRENT request is authenticated with, since it was issued before
 * the change. Re-issuing the auth cookie immediately after, the same way
 * AuthRestController::login() does after a fresh login, keeps the veli
 * signed in through their own password change instead of silently bouncing
 * them to the login screen on their very next request.
 */
final class AccountRestController extends AbstractRestController
{
    public function __construct(
        private readonly CredentialGatewayInterface $credentials,
        private readonly MustChangePasswordGatewayInterface $mustChangePassword
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/security/password', [
            'methods' => 'PUT',
            'callback' => [$this, 'update'],
            'permission_callback' => [$this, 'isLoggedIn'],
            'args' => [
                'current_password' => ['required' => true, 'type' => 'string'],
                'new_password' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $currentPassword = (string) $request->get_param('current_password');
        $newPassword = (string) $request->get_param('new_password');

        if (!$this->credentials->verifyPassword($userId, $currentPassword)) {
            return new WP_REST_Response(['success' => false, 'reason' => 'invalid_password'], 403);
        }

        if (!PasswordPolicy::isAcceptable($newPassword)) {
            return new WP_REST_Response(['success' => false, 'reason' => 'weak_password'], 422);
        }

        wp_set_password($newPassword, $userId);
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId);
        $this->mustChangePassword->clear($userId);

        return new WP_REST_Response(['success' => true]);
    }
}
