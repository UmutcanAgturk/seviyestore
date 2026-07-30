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
use Seviye\Security\TwoFactor\PendingTwoFactorLoginService;
use Seviye\Security\TwoFactor\TwoFactorService;
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
 *
 * login()'s two-step 2FA flow: a password-only success for a user with 2FA
 * enabled does NOT set the auth cookie - it returns a short-lived
 * `pending_token` (see {@see PendingTwoFactorLoginService}) and the client
 * must call login2fa() with a valid code before a session is actually
 * created. This mirrors why every failure path shares one generic outcome
 * above: a caller must never be able to tell "wrong password" apart from
 * "right password, now enter your code" without a session ever having been
 * granted for a mere password match alone.
 */
final class AuthRestController extends AbstractRestController
{
    private const FORGOT_PASSWORD_MAX_ATTEMPTS = 3;
    private const FORGOT_PASSWORD_DECAY_SECONDS = 900;
    private const TWO_FACTOR_MAX_ATTEMPTS = 5;
    private const TWO_FACTOR_DECAY_SECONDS = 900;

    public function __construct(
        private readonly AuthService $authService,
        private readonly IdentityGatewayInterface $identities,
        private readonly PasswordTokenService $tokens,
        private readonly EventBusInterface $eventBus,
        private readonly RateLimiter $rateLimiter,
        private readonly TwoFactorService $twoFactor,
        private readonly PendingTwoFactorLoginService $pendingTwoFactorLogins
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

        register_rest_route(RestApiRegistrar::NAMESPACE, '/auth/login/2fa', [
            'methods' => 'POST',
            'callback' => [$this, 'login2fa'],
            'permission_callback' => '__return_true',
            'args' => [
                'pending_token' => ['required' => true, 'type' => 'string'],
                'code' => ['required' => true, 'type' => 'string'],
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

        $remember = (bool) $request->get_param('remember');

        if ($this->twoFactor->isEnabledForUser($result->userId)) {
            return new WP_REST_Response([
                'success' => true,
                'requires_2fa' => true,
                'pending_token' => $this->pendingTwoFactorLogins->begin($result->userId, $remember),
            ]);
        }

        return $this->finishLogin($result->userId, $remember);
    }

    public function login2fa(WP_REST_Request $request): WP_REST_Response
    {
        $pending = $this->pendingTwoFactorLogins->resolve((string) $request->get_param('pending_token'));

        if ($pending === null) {
            return new WP_REST_Response(['success' => false, 'reason' => 'invalid_token'], 401);
        }

        // Single-use regardless of outcome: a pending login gets exactly one
        // code guess, so brute-forcing a 6-digit code would require a fresh
        // password login (already rate-limited) per attempt.
        $this->pendingTwoFactorLogins->consume((string) $request->get_param('pending_token'));

        $throttleKey = '2fa:' . $pending->userId;

        if ($this->rateLimiter->tooManyAttempts($throttleKey, self::TWO_FACTOR_MAX_ATTEMPTS)) {
            return new WP_REST_Response(['success' => false], 429);
        }

        if (!$this->twoFactor->verifyCode($pending->userId, (string) $request->get_param('code'))) {
            $this->rateLimiter->hit($throttleKey, self::TWO_FACTOR_DECAY_SECONDS);

            return new WP_REST_Response(['success' => false, 'reason' => 'invalid_code'], 401);
        }

        $this->rateLimiter->clear($throttleKey);

        return $this->finishLogin($pending->userId, $pending->remember);
    }

    private function finishLogin(int $userId, bool $remember): WP_REST_Response
    {
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, $remember);

        $user = get_userdata($userId);
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
