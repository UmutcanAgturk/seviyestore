<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Depo\Repository\WpdbPurchaseSuggestionSummary;
use Seviye\Depo\Tests\Fakes\FakeConnection;

final class WpdbPurchaseSuggestionSummaryTest extends TestCase
{
    public function testReturnsThePendingCount(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => '3']];
        $summary = new WpdbPurchaseSuggestionSummary($connection);

        self::assertSame(3, $summary->pendingCount());
        self::assertStringContainsString('status = pending', $connection->queriedSql[0]);
    }

    public function testReturnsZeroWhenNoRowIsReturned(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $summary = new WpdbPurchaseSuggestionSummary($connection);

        self::assertSame(0, $summary->pendingCount());
    }
}
