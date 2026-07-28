<?php

declare(strict_types=1);

namespace Seviye\Core\Events;

/**
 * Pure-PHP publish/subscribe bus used for inter-module communication.
 *
 * It intentionally does not wrap WordPress' do_action()/apply_filters() as its
 * primary mechanism: a typed, in-process bus keeps module contracts explicit
 * and fully unit-testable without a WordPress bootstrap. Every dispatch is
 * still mirrored to a `seviye/core/event/{name}` WordPress action so themes,
 * mu-plugins, or Elementor integrations can hook in the conventional way.
 */
final class EventBus implements EventBusInterface
{
    private const WP_HOOK_PREFIX = 'seviye/core/event/';

    /** @var array<string, array<int, list<callable>>> */
    private array $listeners = [];

    public function listen(string $eventName, callable $listener, int $priority = 10): void
    {
        $this->listeners[$eventName][$priority][] = $listener;
    }

    public function dispatch(Event $event): Event
    {
        $listenersByPriority = $this->listeners[$event->name()] ?? [];
        ksort($listenersByPriority);

        foreach ($listenersByPriority as $listeners) {
            foreach ($listeners as $listener) {
                if ($event->isPropagationStopped()) {
                    break 2;
                }

                $listener($event);
            }
        }

        if (function_exists('do_action')) {
            do_action(self::WP_HOOK_PREFIX . $event->name(), $event);
        }

        return $event;
    }
}
