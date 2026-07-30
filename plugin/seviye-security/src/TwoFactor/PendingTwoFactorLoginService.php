<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

use Seviye\Core\Cache\CacheInterface;

/**
 * Bridges AuthService's password-verified moment and TwoFactorService's
 * code-verified moment: a short-lived, single-use, random token standing
 * in for "this request already proved the password, still needs a TOTP
 * code" - deliberately Core's CacheInterface (a transient), not a database
 * table, since this state is ephemeral by nature (5-minute TTL) the same
 * way RateLimiter's attempt counters are.
 */
final class PendingTwoFactorLoginService
{
    private const TTL_SECONDS = 300;

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function begin(int $userId, bool $remember): string
    {
        $token = bin2hex(random_bytes(32));
        $this->cache->put($this->cacheKey($token), new PendingTwoFactorLogin($userId, $remember), self::TTL_SECONDS);

        return $token;
    }

    public function resolve(string $token): ?PendingTwoFactorLogin
    {
        $value = $this->cache->get($this->cacheKey($token));

        return $value instanceof PendingTwoFactorLogin ? $value : null;
    }

    /**
     * Single-use: called once a login attempt using this token has been
     * resolved (successfully or not), so a leaked/replayed token can never
     * be tried against multiple codes.
     */
    public function consume(string $token): void
    {
        $this->cache->forget($this->cacheKey($token));
    }

    private function cacheKey(string $token): string
    {
        return 'pending_2fa_login:' . hash('sha256', $token);
    }
}
