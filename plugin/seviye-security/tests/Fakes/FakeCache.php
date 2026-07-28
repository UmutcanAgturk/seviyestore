<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Fakes;

use Seviye\Core\Cache\CacheInterface;

final class FakeCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $storage = [];

    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->storage[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->storage[$key]);
    }
}
