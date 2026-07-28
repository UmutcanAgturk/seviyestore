<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

use Psr\Log\LoggerInterface;
use Seviye\Core\Security\RateLimiter;
use Seviye\Security\Identity\IdentityGatewayInterface;

/**
 * Authenticates a login attempt made with a T.C. Kimlik No and password.
 *
 * Every failure path - malformed number, unknown number, wrong password -
 * returns the same {@see AuthFailureReason::INVALID_CREDENTIALS} and takes
 * the same rate-limit hit, so a caller (or an attacker) can never tell
 * "this T.C. Kimlik No isn't registered" from "the password is wrong".
 */
final class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 900;

    public function __construct(
        private readonly IdentityGatewayInterface $identities,
        private readonly CredentialGatewayInterface $credentials,
        private readonly RateLimiter $rateLimiter,
        private readonly LoggerInterface $logger
    ) {
    }

    public function attempt(string $rawTcNumber, string $password): AuthResult
    {
        $throttleKey = $this->throttleKey($rawTcNumber);

        if ($this->rateLimiter->tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $this->logger->warning('Login throttled.', ['channel' => 'security.auth']);

            return AuthResult::throttled();
        }

        if (!TcNumber::isValid($rawTcNumber)) {
            return $this->fail($throttleKey, null);
        }

        $userId = $this->identities->findUserIdByTcNumber(TcNumber::fromString($rawTcNumber));

        if ($userId === null || !$this->credentials->verifyPassword($userId, $password)) {
            return $this->fail($throttleKey, $userId);
        }

        $this->rateLimiter->clear($throttleKey);
        $this->logger->info('Login succeeded.', ['channel' => 'security.auth', 'user_id' => $userId]);

        return AuthResult::success($userId);
    }

    private function fail(string $throttleKey, ?int $userId): AuthResult
    {
        $this->rateLimiter->hit($throttleKey, self::DECAY_SECONDS);
        $this->logger->warning('Login failed.', ['channel' => 'security.auth', 'user_id' => $userId]);

        return AuthResult::invalidCredentials();
    }

    /**
     * Hashed so the raw T.C. Kimlik No never ends up stored in a cache
     * backend (transient option names, object cache keys, ...) in plain text.
     */
    private function throttleKey(string $rawTcNumber): string
    {
        return 'tc:' . hash('sha256', $rawTcNumber);
    }
}
