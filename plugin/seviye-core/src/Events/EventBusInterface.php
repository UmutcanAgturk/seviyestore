<?php

declare(strict_types=1);

namespace Seviye\Core\Events;

interface EventBusInterface
{
    /**
     * Registers a listener for an event name. Lower priority values run first.
     *
     * @param callable(Event): void $listener
     */
    public function listen(string $eventName, callable $listener, int $priority = 10): void;

    public function dispatch(Event $event): Event;
}
