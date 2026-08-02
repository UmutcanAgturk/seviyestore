<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Events\Event;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Support\OrderStatusNotificationListener;
use Seviye\Notifications\Tests\Fakes\FakeNotificationDispatcher;

final class OrderStatusNotificationListenerTest extends TestCase
{
    public function testCancelledOrderDispatchesAnEmailNotification(): void
    {
        $dispatcher = new FakeNotificationDispatcher();
        $listener = new OrderStatusNotificationListener($dispatcher);

        $listener->onOrderCancelled(new Event('commerce.order_cancelled', [
            'customer_id' => 12,
            'order_number' => '1042',
            'total' => 150.0,
            'items' => [],
        ]));

        self::assertCount(1, $dispatcher->calls);
        $call = $dispatcher->calls[0];
        self::assertSame(12, $call['userId']);
        self::assertSame(NotificationChannel::EMAIL, $call['channel']);
        self::assertSame('commerce.order_cancelled', $call['eventName']);
        self::assertStringContainsString('#1042', $call['subject']);
        self::assertStringContainsString('iptal edilmiştir', $call['body']);
    }

    public function testRefundedOrderDispatchesAnEmailWithTheRefundedAmount(): void
    {
        $dispatcher = new FakeNotificationDispatcher();
        $listener = new OrderStatusNotificationListener($dispatcher);

        $listener->onOrderRefunded(new Event('commerce.order_refunded', [
            'customer_id' => 12,
            'order_number' => '1042',
            'total' => 150.0,
            'items' => [],
            'refunded_amount' => 45.5,
            'reason' => 'Hasarlı ürün',
        ]));

        self::assertCount(1, $dispatcher->calls);
        $call = $dispatcher->calls[0];
        self::assertStringContainsString('45,50 TRY', $call['body']);
        self::assertStringContainsString('Hasarlı ürün', $call['body']);
    }

    public function testNoReasonOmitsTheNoteLine(): void
    {
        $dispatcher = new FakeNotificationDispatcher();
        $listener = new OrderStatusNotificationListener($dispatcher);

        $listener->onOrderRefunded(new Event('commerce.order_refunded', [
            'customer_id' => 12,
            'order_number' => '1042',
            'total' => 150.0,
            'items' => [],
            'refunded_amount' => 45.5,
        ]));

        self::assertStringNotContainsString('Not:', $dispatcher->calls[0]['body']);
    }

    public function testUnresolvableCustomerSkipsDispatch(): void
    {
        $dispatcher = new FakeNotificationDispatcher();
        $listener = new OrderStatusNotificationListener($dispatcher);

        $listener->onOrderCancelled(new Event('commerce.order_cancelled', [
            'customer_id' => 0,
            'order_number' => '1042',
        ]));

        self::assertCount(0, $dispatcher->calls);
    }
}
