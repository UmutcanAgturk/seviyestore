<?php

declare(strict_types=1);

namespace Seviye\Core\Security;

use Seviye\Core\Cache\CacheInterface;

/**
 * Fixed-window attempt counter, primarily used to throttle the custom
 * TC Kimlik No + password login form against brute-force attempts.
 */
final class RateLimiter
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $attempts = $this->attempts($key) + 1;
        $this->cache->put($this->attemptsKey($key), $attempts, $decaySeconds);

        return $attempts;
    }

    public function attempts(string $key): int
    {
        return (int) ($this->cache->get($this->attemptsKey($key)) ?? 0);
    }

    public function clear(string $key): void
    {
        $this->cache->forget($this->attemptsKey($key));
    }

    private function attemptsKey(string $key): string
    {
        return 'rate_limit:' . $key;
    }
}
