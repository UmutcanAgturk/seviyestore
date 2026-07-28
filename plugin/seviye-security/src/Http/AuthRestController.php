<?php

declare(strict_types=1);

namespace Seviye\Security\Http;

use Seviye\Core\Events\Event;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Security\RateLimiter;
use Seviye\Security\Auth\AuthFailureReason;
use Seviye\Security\Auth\AuthService;
use Seviye\Security\Auth\PasswordPolicy;
use Seviye\Security\Auth\TcNumber;
use Seviye\Security\Identity\IdentityGatewayInterface;
use Seviye\Security\Routing\RoleRouter;
use Seviye\Security\Token\PasswordTokenPurpose;
use Seviye\Security\Token\PasswordTokenService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public (pre-login) endpoints under seviye/v1/auth/*. No nonce is required:
 * these requests are made before a session exists, so there is no auth
 * cookie for WordPress' own nonce-vs-cookie CSRF check to protect - the
 * relevant defense here is {@see RateLimiter}, not a nonce.
 *
 * Never sends "this identity/token does not exist" as a distinct outcome
 * from "wrong credential" - see {@see AuthFailureReason} - to avoid turning
 * these endpoints into a T.C. Kimlik No enumeration oracle.
 */
final class AuthRestController extends AbstractRestController
{
    private const FORGOT_PASSWORD_MAX_ATTEMPTS = 3;
    private const FORGOT_PASSWORD_DECAY_SECONDS = 900;

    public function __construct(
        private readonly AuthService $authService,
        private readonly IdentityGatewayInterface $identities,
        private readonly PasswordTokenService $tokens,
        private readonly EventBusInterface $eventBus,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/auth/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
            'args' => [
                'tc_no' => ['required' => true, 'type' => 'string'],
                'password' => ['required' => true, 'type' => 'string'],
                'remember' => ['required' => false, 'type' => 'boolean', 'default' => false],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/auth/forgot-password', [
            'methods' => 'POST',
            'callback' => [$this, 'forgotPassword'],
            'permission_callback' => '__return_true',
            'args' => [
                'tc_no' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/auth/first-password', [
            'methods' => 'POST',
            'callback' => [$this, 'firstPasswordSetup'],
            'permission_callback' => '__return_true',
            'args' => [
                'tc_no' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/auth/set-password', [
            'methods' => 'POST',
            'callback' => [$this, 'setPassword'],
            'permission_callback' => '__return_true',
            'args' => [
                'token' => ['required' => true, 'type' => 'string'],
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public function login(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->authService->attempt(
            (string) $request->get_param('tc_no'),
            (string) $request->get_param('password')
        );

        if (!$result->successful) {
            $status = $result->failureReason === AuthFailureReason::THROTTLED ? 429 : 401;

            return new WP_REST_Response(['success' => false], $status);
        }

        wp_set_current_user($result->userId);
        wp_set_auth_cookie($result->userId, (bool) $request->get_param('remember'));

        $user = get_userdata($result->userId);
        $roles = $user !== false ? array_values($user->roles) : [];

        return new WP_REST_Response([
            'success' => true,
            'roles' => $roles,
            'redirect_url' => RoleRouter::landingPathFor($roles),
        ]);
    }

    public function forgotPassword(WP_REST_Request $request): WP_REST_Response
    {
        return $this->requestPasswordToken($request, PasswordTokenPurpose::RESET);
    }

    public function firstPasswordSetup(WP_REST_Request $request): WP_REST_Response
    {
        return $this->requestPasswordToken($request, PasswordTokenPurpose::FIRST_SETUP);
    }

    private function requestPasswordToken(WP_REST_Request $request, PasswordTokenPurpose $purpose): WP_REST_Response
    {
        $rawTcNumber = (string) $request->get_param('tc_no');
        $throttleKey = $purpose->value . ':' . hash('sha256', $rawTcNumber);

        if ($this->rateLimiter->tooManyAttempts($throttleKey, self::FORGOT_PASSWORD_MAX_ATTEMPTS)) {
            return new WP_REST_Response(['success' => false], 429);
        }

        $this->rateLimiter->hit($throttleKey, self::FORGOT_PASSWORD_DECAY_SECONDS);

        if (TcNumber::isValid($rawTcNumber)) {
            $userId = $this->identities->findUserIdByTcNumber(TcNumber::fromString($rawTcNumber));

            if ($userId !== null) {
                $token = $this->tokens->issue($userId, $purpose);

                $this->eventBus->dispatch(new Event('security.password_reset_requested', [
                    'user_id' => $userId,
                    'token' => $token,
                    'purpose' => $purpose->value,
                ]));
            }
        }

        // Always the same response, whether or not the T.C. Kimlik No is registered.
        return new WP_REST_Response(['success' => true]);
    }

    public function setPassword(WP_REST_Request $request): WP_REST_Response
    {
        $newPassword = (string) $request->get_param('password');

        if (!PasswordPolicy::isAcceptable($newPassword)) {
            return new WP_REST_Response(['success' => false, 'reason' => 'weak_password'], 422);
        }

        $record = $this->tokens->redeem((string) $request->get_param('token'));

        if ($record === null) {
            return new WP_REST_Response(['success' => false, 'reason' => 'invalid_token'], 400);
        }

        wp_set_password($newPassword, $record->userId);

        $this->eventBus->dispatch(new Event('security.password_set', [
            'user_id' => $record->userId,
            'purpose' => $record->purpose->value,
        ]));

        return new WP_REST_Response(['success' => true]);
    }
}
