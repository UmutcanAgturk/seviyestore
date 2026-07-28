<?php

declare(strict_types=1);

namespace Seviye\Core\Container;

use Closure;
use Psr\Container\ContainerInterface;
use Seviye\Core\Container\Exception\NotFoundException;

/**
 * Minimal PSR-11 dependency injection container.
 *
 * Every cross-module service (event bus, RBAC, migrations, logging...) is
 * resolved through this container instead of being instantiated ad hoc, so
 * modules depend on Core's published contracts rather than on each other.
 */
final class ServiceContainer implements ContainerInterface
{
    /** @var array<string, Closure(self): mixed> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Registers a factory that produces a fresh instance on every {@see get()} call.
     *
     * @param Closure(self): mixed $factory
     */
    public function bind(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        $this->shared[$id] = false;
        unset($this->instances[$id]);
    }

    /**
     * Registers a factory whose result is cached and reused for every subsequent {@see get()} call.
     *
     * @param Closure(self): mixed $factory
     */
    public function singleton(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new NotFoundException(sprintf('No binding registered for "%s".', $id));
        }

        $object = ($this->bindings[$id])($this);

        if ($this->shared[$id]) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || array_key_exists($id, $this->instances);
    }
}
