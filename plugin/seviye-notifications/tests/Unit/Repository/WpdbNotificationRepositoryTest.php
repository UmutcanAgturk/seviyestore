<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Domain\NotificationStatus;
use Seviye\Notifications\Repository\WpdbNotificationRepository;
use Seviye\Notifications\Tests\Fakes\FakeConnection;

final class WpdbNotificationRepositoryTest extends TestCase
{
    public function testRecordInsertsAsPendingAndReadsBackByLastInsertId(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 5;
        $connection->resultsToReturn = [$this->row(5, 12, 'email', 'security.password_reset_requested')];
        $repository = new WpdbNotificationRepository($connection);

        $notification = $repository->record(
            12,
            NotificationChannel::EMAIL,
            'security.password_reset_requested',
            'Şifre Sıfırlama',
            'Bağlantı: ...'
        );

        self::assertSame(5, $notification->id);
        self::assertSame(12, $notification->userId);
        self::assertSame(NotificationChannel::EMAIL, $notification->channel);
        self::assertSame(NotificationStatus::PENDING, $notification->status);

        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_notifications', $table);
        self::assertSame('pending', $data['status']);
        self::assertSame('email', $data['channel']);
    }

    public function testMarkSentUpdatesStatusAndSentAt(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbNotificationRepository($connection);

        $repository->markSent(5);

        self::assertStringContainsString('SET status = sent', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 5', $connection->queries[0]);
    }

    public function testMarkFailedUpdatesStatusAndError(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbNotificationRepository($connection);

        $repository->markFailed(5, 'SMTP timeout');

        self::assertStringContainsString('SET status = failed', $connection->queries[0]);
        self::assertStringContainsString('error = SMTP timeout', $connection->queries[0]);
    }

    public function testMarkReadIsScopedToTheOwningUser(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbNotificationRepository($connection);

        $repository->markRead(5, 12);

        self::assertStringContainsString('WHERE id = 5 AND user_id = 12', $connection->queries[0]);
        self::assertStringContainsString('read_at IS NULL', $connection->queries[0]);
    }

    public function testFindForUserHydratesEveryRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            $this->row(2, 12, 'panel', 'commerce.order_line_item_completed'),
            $this->row(1, 12, 'email', 'security.password_reset_requested'),
        ];
        $repository = new WpdbNotificationRepository($connection);

        $notifications = $repository->findForUser(12);

        self::assertCount(2, $notifications);
        self::assertSame(2, $notifications[0]->id);
        self::assertSame(1, $notifications[1]->id);
    }

    public function testFindForUserFiltersByChannelWhenGiven(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbNotificationRepository($connection);

        $repository->findForUser(12, NotificationChannel::PANEL);

        self::assertStringContainsString('AND channel = panel', $connection->queriedSql[0] ?? '');
    }

    public function testUnreadCountForUserIsZeroWhenNoRowsMatch(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => '0']];
        $repository = new WpdbNotificationRepository($connection);

        self::assertSame(0, $repository->unreadCountForUser(999));
    }

    public function testUnreadCountForUserCountsOnlyUnreadPanelRows(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => '3']];
        $repository = new WpdbNotificationRepository($connection);

        self::assertSame(3, $repository->unreadCountForUser(12));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id, int $userId, string $channel, string $eventName): array
    {
        return [
            'id' => (string) $id,
            'user_id' => (string) $userId,
            'channel' => $channel,
            'event_name' => $eventName,
            'subject' => 'Konu',
            'body' => 'İçerik',
            'status' => 'pending',
            'error' => null,
            'created_at' => '2026-07-30 12:00:00',
            'sent_at' => null,
            'read_at' => null,
        ];
    }
}
