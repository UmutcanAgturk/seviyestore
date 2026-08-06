<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Events\Event;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Support\BackInStockNotificationListener;
use Seviye\Notifications\Tests\Fakes\FakeNotificationDispatcher;

final class BackInStockNotificationListenerTest extends TestCase
{
    public function testDispatchesPanelAndEmailNotificationsToTheSubscriber(): void
    {
        $dispatcher = new FakeNotificationDispatcher();
        $listener = new BackInStockNotificationListener($dispatcher);

        $listener->onStockSubscriptionFulfilled(new Event('commerce.stock_subscription_fulfilled', [
            'user_id' => 12,
            'product_id' => 42,
            'product_name' => 'A4 Kareli Defter',
        ]));

        self::assertCount(2, $dispatcher->calls);

        $channels = array_map(static fn (array $call): NotificationChannel => $call['channel'], $dispatcher->calls);
        self::assertContains(NotificationChannel::PANEL, $channels);
        self::assertContains(NotificationChannel::EMAIL, $channels);

        foreach ($dispatcher->calls as $call) {
            self::assertSame(12, $call['userId']);
            self::assertSame('commerce.stock_subscription_fulfilled', $call['eventName']);
            self::assertStringContainsString('A4 Kareli Defter', $call['subject']);
            self::assertStringContainsString('A4 Kareli Defter', $call['body']);
        }
    }

    public function testUnresolvableUserSkipsDispatch(): void
    {
        $dispatcher = new FakeNotificationDispatcher();
        $listener = new BackInStockNotificationListener($dispatcher);

        $listener->onStockSubscriptionFulfilled(new Event('commerce.stock_subscription_fulfilled', [
            'user_id' => 0,
            'product_id' => 42,
            'product_name' => 'A4 Kareli Defter',
        ]));

        self::assertCount(0, $dispatcher->calls);
    }
}
