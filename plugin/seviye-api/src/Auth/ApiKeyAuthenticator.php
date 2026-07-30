<?php

declare(strict_types=1);

namespace Seviye\Api\Auth;

use Seviye\Api\Repository\ApiKeyRepositoryInterface;
use Seviye\Api\Support\ApiKeyGenerator;
use Seviye\Core\Security\RateLimiter;

/**
 * Fully pure and unit-testable, unlike the Http adapter that calls it
 * ({@see \Seviye\Api\Http\ApiKeyAuthHook}): it only touches this module's
 * own repository and Core's RateLimiter, never a WordPress superglobal or
 * `wp_set_current_user()` directly.
 *
 * Throttled by client IP (not by the presented key, which an attacker
 * controls and can freely rotate) - the same MAX_ATTEMPTS/DECAY_SECONDS
 * shape as Security\Auth\AuthService's login throttle, applied to a
 * different credential type.
 */
final class ApiKeyAuthenticator
{
    private const MAX_ATTEMPTS = 10;
    private const DECAY_SECONDS = 900;

    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeys,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function authenticate(string $rawKey, string $clientIp): ApiKeyAuthResult
    {
        $throttleKey = $this->throttleKey($clientIp);

        if ($this->rateLimiter->tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            return ApiKeyAuthResult::throttled();
        }

        $apiKey = $this->apiKeys->findByHash(ApiKeyGenerator::hash($rawKey));

        if ($apiKey === null || $apiKey->isRevoked()) {
            $this->rateLimiter->hit($throttleKey, self::DECAY_SECONDS);

            return ApiKeyAuthResult::invalid();
        }

        $this->rateLimiter->clear($throttleKey);
        $this->apiKeys->touchLastUsed($apiKey->id);

        return ApiKeyAuthResult::success($apiKey->userId);
    }

    private function throttleKey(string $clientIp): string
    {
        return 'api_key:' . ($clientIp !== '' ? $clientIp : 'unknown');
    }
}
