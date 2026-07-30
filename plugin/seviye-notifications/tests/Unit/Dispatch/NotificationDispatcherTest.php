<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Dispatch;

use PHPUnit\Framework\TestCase;
use Seviye\Notifications\Dispatch\NotificationDispatcher;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Domain\NotificationStatus;
use Seviye\Notifications\Tests\Fakes\FakeChannel;
use Seviye\Notifications\Tests\Fakes\FakeNotificationRepository;
use Seviye\Notifications\Tests\Fakes\FakeRecipientResolver;

final class NotificationDispatcherTest extends TestCase
{
    public function testSuccessfulDeliveryMarksTheNotificationSent(): void
    {
        $repository = new FakeNotificationRepository();
        $recipients = new FakeRecipientResolver();
        $recipients->addresses['12:email'] = 'veli@example.com';
        $channel = new FakeChannel(succeeds: true);
        $dispatcher = new NotificationDispatcher($repository, $recipients, [
            NotificationChannel::EMAIL->value => $channel,
        ]);

        $dispatcher->dispatch(12, NotificationChannel::EMAIL, 'security.password_reset_requested', 'Konu', 'Gövde');

        self::assertCount(1, $repository->notifications);
        self::assertSame(NotificationStatus::SENT, $repository->notifications[0]->status);
        self::assertSame('veli@example.com', $channel->sent[0]['recipient']);
    }

    public function testTransportFailureMarksTheNotificationFailed(): void
    {
        $repository = new FakeNotificationRepository();
        $recipients = new FakeRecipientResolver();
        $recipients->addresses['12:email'] = 'veli@example.com';
        $dispatcher = new NotificationDispatcher($repository, $recipients, [
            NotificationChannel::EMAIL->value => new FakeChannel(succeeds: false),
        ]);

        $dispatcher->dispatch(12, NotificationChannel::EMAIL, 'security.password_reset_requested', 'Konu', 'Gövde');

        self::assertSame(NotificationStatus::FAILED, $repository->notifications[0]->status);
        self::assertNotNull($repository->notifications[0]->error);
    }

    public function testMissingRecipientMarksFailedWithoutCallingTheChannel(): void
    {
        $repository = new FakeNotificationRepository();
        $recipients = new FakeRecipientResolver();
        $channel = new FakeChannel();
        $dispatcher = new NotificationDispatcher($repository, $recipients, [
            NotificationChannel::SMS->value => $channel,
        ]);

        $dispatcher->dispatch(12, NotificationChannel::SMS, 'commerce.order_line_item_completed', 'Konu', 'Gövde');

        self::assertSame(NotificationStatus::FAILED, $repository->notifications[0]->status);
        self::assertCount(0, $channel->sent);
    }

    public function testUnconfiguredChannelMarksFailed(): void
    {
        $repository = new FakeNotificationRepository();
        $recipients = new FakeRecipientResolver();
        $recipients->addresses['12:sms'] = '+905551234567';
        $dispatcher = new NotificationDispatcher($repository, $recipients, []);

        $dispatcher->dispatch(12, NotificationChannel::SMS, 'commerce.order_line_item_completed', 'Konu', 'Gövde');

        self::assertSame(NotificationStatus::FAILED, $repository->notifications[0]->status);
    }

    public function testPanelChannelGoesThroughTheSameFlowAsEveryOtherChannel(): void
    {
        $repository = new FakeNotificationRepository();
        $recipients = new FakeRecipientResolver();
        $recipients->addresses['12:panel'] = '12';
        $dispatcher = new NotificationDispatcher($repository, $recipients, [
            NotificationChannel::PANEL->value => new FakeChannel(succeeds: true),
        ]);

        $dispatcher->dispatch(12, NotificationChannel::PANEL, 'commerce.order_line_item_completed', 'Konu', 'Gövde');

        self::assertSame(NotificationStatus::SENT, $repository->notifications[0]->status);
    }
}
