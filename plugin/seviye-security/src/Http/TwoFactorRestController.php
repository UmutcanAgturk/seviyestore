<?php

declare(strict_types=1);

namespace Seviye\Security\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Security\Auth\CredentialGatewayInterface;
use Seviye\Security\TwoFactor\TwoFactorService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/security/2fa/* - self-service, authenticated (cookie+nonce,
 * same as every other post-login panel endpoint in this platform). Not
 * gated by any capability: every role manages its own account's 2FA, this
 * is a personal security setting, not a permission-scoped resource like
 * everything else the platform's RBAC governs.
 */
final class TwoFactorRestController extends AbstractRestController
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly CredentialGatewayInterface $credentials
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/security/2fa/status', [
            'methods' => 'GET',
            'callback' => [$this, 'status'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/security/2fa/setup', [
            'methods' => 'POST',
            'callback' => [$this, 'setup'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/security/2fa/confirm', [
            'methods' => 'POST',
            'callback' => [$this, 'confirm'],
            'permission_callback' => [$this, 'isLoggedIn'],
            'args' => [
                'code' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/security/2fa/disable', [
            'methods' => 'POST',
            'callback' => [$this, 'disable'],
            'permission_callback' => [$this, 'isLoggedIn'],
            'args' => [
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function status(): WP_REST_Response
    {
        return new WP_REST_Response(['enabled' => $this->twoFactor->isEnabledForUser(get_current_user_id())]);
    }

    public function setup(): WP_REST_Response
    {
        $user = wp_get_current_user();
        $setup = $this->twoFactor->beginSetup(get_current_user_id(), $user->user_login);

        return new WP_REST_Response([
            'secret' => $setup->secret,
            'otpauth_uri' => $setup->otpauthUri,
        ]);
    }

    public function confirm(WP_REST_Request $request): WP_REST_Response
    {
        $confirmed = $this->twoFactor->confirmSetup(get_current_user_id(), (string) $request->get_param('code'));

        if (!$confirmed) {
            return new WP_REST_Response(['success' => false], 422);
        }

        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Requires the current password again (not just an active session)
     * before turning 2FA off - the same "prove you're still you" bar most
     * platforms set for a security-downgrading action.
     */
    public function disable(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $password = (string) $request->get_param('password');

        if (!$this->credentials->verifyPassword($userId, $password)) {
            return new WP_REST_Response(['success' => false, 'reason' => 'invalid_password'], 403);
        }

        $this->twoFactor->disable($userId);

        return new WP_REST_Response(['success' => true]);
    }
}
