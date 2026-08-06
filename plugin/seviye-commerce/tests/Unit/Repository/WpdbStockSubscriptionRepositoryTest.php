<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Commerce\Repository\WpdbStockSubscriptionRepository;
use Seviye\Commerce\Tests\Fakes\FakeConnection;

final class WpdbStockSubscriptionRepositoryTest extends TestCase
{
    public function testSubscribeUpsertsARow(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbStockSubscriptionRepository($connection);

        $repository->subscribe(5, 7);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $connection->queries[0]);
    }

    public function testUnsubscribeDeletesTheRow(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbStockSubscriptionRepository($connection);

        $repository->unsubscribe(5, 7);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('DELETE FROM', $connection->queries[0]);
    }

    public function testIsSubscribedFalseWhenNoRowExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbStockSubscriptionRepository($connection);

        self::assertFalse($repository->isSubscribed(5, 7));
    }

    public function testIsSubscribedTrueWhenARowExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['id' => '1']];
        $repository = new WpdbStockSubscriptionRepository($connection);

        self::assertTrue($repository->isSubscribed(5, 7));
    }

    public function testSubscriberIdsForReturnsIntegerIds(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['user_id' => '7'], ['user_id' => '9']];
        $repository = new WpdbStockSubscriptionRepository($connection);

        self::assertSame([7, 9], $repository->subscriberIdsFor(5));
    }

    public function testDeleteAllForRemovesEveryRowForTheProduct(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbStockSubscriptionRepository($connection);

        $repository->deleteAllFor(5);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('DELETE FROM', $connection->queries[0]);
    }
}
