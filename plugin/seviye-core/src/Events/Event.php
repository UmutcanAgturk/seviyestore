<?php

declare(strict_types=1);

namespace Seviye\Core\Events;

/**
 * A named, typed message dispatched through the {@see EventBus}.
 *
 * Modules communicate cross-cutting occurrences ("student.enrolled",
 * "order.paid"...) through events instead of calling each other directly.
 */
final class Event
{
    private bool $propagationStopped = false;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $name,
        private array $payload = []
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->payload[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->payload;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
