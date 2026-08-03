<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Domain\ScheduledBroadcastStatus;
use Seviye\Notifications\Repository\WpdbScheduledBroadcastRepository;
use Seviye\Notifications\Tests\Fakes\FakeConnection;

final class WpdbScheduledBroadcastRepositoryTest extends TestCase
{
    /**
     * Same accepted limitation as other repositories' create() tests in
     * this codebase (e.g. WpdbPurchaseOrderRepositoryTest) - the fake
     * connection can't distinguish create()'s insert from its own
     * readback query, so the readback throws here; the insert itself is
     * still fully assertable.
     */
    public function testCreateInsertsTheRowWithEncodedChannels(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 9;
        $connection->resultsToReturn = [];
        $repository = new WpdbScheduledBroadcastRepository($connection);

        try {
            $repository->create(
                7,
                3,
                'Yılsonu Etkinliği',
                'Yılsonu etkinliğimiz 20 Haziran\'da.',
                [NotificationChannel::EMAIL, NotificationChannel::PANEL],
                '2026-06-15 09:00:00'
            );
            self::fail('Expected a RuntimeException from the unreadable final find().');
        } catch (RuntimeException) {
            // Expected - see method docblock.
        }

        self::assertCount(1, $connection->inserted);
        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_scheduled_broadcasts', $table);
        self::assertSame(7, $data['created_by']);
        self::assertSame(3, $data['branch_id']);
        self::assertSame('email,panel', $data['channels']);
        self::assertSame('pending', $data['status']);
        self::assertSame('2026-06-15 09:00:00', $data['scheduled_at']);
    }

    public function testFindHydratesChannelsAndBranchId(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(9, 7, 3, 'email,panel', 'pending')];
        $repository = new WpdbScheduledBroadcastRepository($connection);

        $broadcast = $repository->find(9);

        self::assertNotNull($broadcast);
        self::assertSame(3, $broadcast->branchId);
        self::assertSame([NotificationChannel::EMAIL, NotificationChannel::PANEL], $broadcast->channels);
        self::assertSame(ScheduledBroadcastStatus::PENDING, $broadcast->status);
    }

    public function testFindTreatsAZeroBranchIdAsPlatformWide(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(9, 7, 0, 'email', 'pending')];
        $repository = new WpdbScheduledBroadcastRepository($connection);

        $broadcast = $repository->find(9);

        self::assertNotNull($broadcast);
        self::assertNull($broadcast->branchId);
    }

    public function testAllForBranchReturnsEmptyListWhenNoneMatch(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbScheduledBroadcastRepository($connection);

        self::assertSame([], $repository->allForBranch(3));
    }

    public function testMarkSentRunsAnUpdateQuerySettingStatusToSent(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbScheduledBroadcastRepository($connection);

        $repository->markSent(9);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('status = sent', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 9', $connection->queries[0]);
    }

    public function testCancelRunsAnUpdateQuerySettingStatusToCancelled(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbScheduledBroadcastRepository($connection);

        $repository->cancel(9);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('status = cancelled', $connection->queries[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id, int $createdBy, int $branchId, string $channels, string $status): array
    {
        return [
            'id' => (string) $id,
            'created_by' => (string) $createdBy,
            'branch_id' => (string) $branchId,
            'subject' => 'Yılsonu Etkinliği',
            'body' => 'Yılsonu etkinliğimiz 20 Haziran\'da.',
            'channels' => $channels,
            'scheduled_at' => '2026-06-15 09:00:00',
            'status' => $status,
            'created_at' => '2026-06-01 10:00:00',
        ];
    }
}
