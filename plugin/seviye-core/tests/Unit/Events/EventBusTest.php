<?php

declare(strict_types=1);

namespace Seviye\Core\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Events\Event;
use Seviye\Core\Events\EventBus;

final class EventBusTest extends TestCase
{
    public function testListenersReceiveDispatchedEventInPriorityOrder(): void
    {
        $bus = new EventBus();
        $calls = [];

        $bus->listen('student.enrolled', static function (Event $event) use (&$calls): void {
            $calls[] = 'late:' . $event->get('name');
        }, 20);

        $bus->listen('student.enrolled', static function (Event $event) use (&$calls): void {
            $calls[] = 'early:' . $event->get('name');
        }, 5);

        $bus->dispatch(new Event('student.enrolled', ['name' => 'Ali']));

        self::assertSame(['early:Ali', 'late:Ali'], $calls);
    }

    public function testStopPropagationPreventsSubsequentListeners(): void
    {
        $bus = new EventBus();
        $calls = [];

        $bus->listen('order.created', static function (Event $event) use (&$calls): void {
            $calls[] = 'first';
            $event->stopPropagation();
        }, 5);

        $bus->listen('order.created', static function () use (&$calls): void {
            $calls[] = 'second';
        }, 10);

        $bus->dispatch(new Event('order.created'));

        self::assertSame(['first'], $calls);
    }

    public function testUnrelatedEventNamesDoNotTriggerListeners(): void
    {
        $bus = new EventBus();
        $calls = [];

        $bus->listen('order.created', static function () use (&$calls): void {
            $calls[] = 'should-not-run';
        });

        $bus->dispatch(new Event('order.refunded'));

        self::assertSame([], $calls);
    }
}
