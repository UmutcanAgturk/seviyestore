<?php

declare(strict_types=1);

namespace Seviye\Core\Cache;

/**
 * WordPress transients adapter for {@see CacheInterface}.
 */
final class TransientCache implements CacheInterface
{
    private const PREFIX = 'scp_';

    public function get(string $key): mixed
    {
        if (!function_exists('get_transient')) {
            return null;
        }

        $value = get_transient(self::PREFIX . $key);

        return $value === false ? null : $value;
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        if (function_exists('set_transient')) {
            set_transient(self::PREFIX . $key, $value, $ttlSeconds);
        }
    }

    public function forget(string $key): void
    {
        if (function_exists('delete_transient')) {
            delete_transient(self::PREFIX . $key);
        }
    }
}
