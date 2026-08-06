<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Events\Event;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Support\PasswordResetNotificationListener;
use Seviye\Notifications\Tests\Fakes\FakeNotificationDispatcher;

final class PasswordResetNotificationListenerTest extends TestCase
{
    public function testDispatchesAnEmailNotificationWithTheResetLink(): void
    {
        $dispatcher = new FakeNotificationDispatcher();
        $listener = new PasswordResetNotificationListener($dispatcher);

        $listener->onPasswordResetRequested(new Event('security.password_reset_requested', [
            'user_id' => 12,
            'token' => 'abc123',
            'purpose' => 'reset',
        ]));

        self::assertCount(1, $dispatcher->calls);
        $call = $dispatcher->calls[0];
        self::assertSame(12, $call['userId']);
        self::assertSame(NotificationChannel::EMAIL, $call['channel']);
        self::assertSame('security.password_reset_requested', $call['eventName']);
        self::assertStringContainsString('scp_token=abc123', $call['body']);
    }

    public function testAnUnrecognizedPurposeFallsBackToTheGenericSubject(): void
    {
        $dispatcher = new FakeNotificationDispatcher();
        $listener = new PasswordResetNotificationListener($dispatcher);

        $listener->onPasswordResetRequested(new Event('security.password_reset_requested', [
            'user_id' => 12,
            'token' => 'abc123',
            'purpose' => 'something_unexpected',
        ]));

        self::assertStringContainsString('Şifre İşlemi', $dispatcher->calls[0]['subject']);
    }

    public function testResetPurposeUsesTheResetSubject(): void
    {
        $dispatcher = new FakeNotificationDispatcher();
        $listener = new PasswordResetNotificationListener($dispatcher);

        $listener->onPasswordResetRequested(new Event('security.password_reset_requested', [
            'user_id' => 12,
            'token' => 'abc123',
            'purpose' => 'reset',
        ]));

        self::assertStringContainsString('Şifre Sıfırlama', $dispatcher->calls[0]['subject']);
    }
}
