<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Students\Contracts\StudentSummary;
use Seviye\Students\Repository\WpdbParentChildrenLookup;
use Seviye\Students\Tests\Fakes\FakeConnection;

final class WpdbParentChildrenLookupTest extends TestCase
{
    public function testChildrenOfHydratesEveryJoinedRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            ['id' => '5', 'branch_id' => '7', 'first_name' => 'Ada', 'last_name' => 'Yılmaz'],
            ['id' => '6', 'branch_id' => '7', 'first_name' => 'Deniz', 'last_name' => 'Yılmaz'],
        ];
        $lookup = new WpdbParentChildrenLookup($connection);

        $children = $lookup->childrenOf(42);

        self::assertEquals(
            [
                new StudentSummary(5, 7, 'Ada', 'Yılmaz'),
                new StudentSummary(6, 7, 'Deniz', 'Yılmaz'),
            ],
            $children
        );
    }

    public function testChildrenOfReturnsEmptyWhenNoLinkedChildren(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $lookup = new WpdbParentChildrenLookup($connection);

        self::assertSame([], $lookup->childrenOf(42));
    }
}
