<?php

declare(strict_types=1);

namespace Seviye\Core\Cache;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, int $ttlSeconds): void;

    public function forget(string $key): void;
}
