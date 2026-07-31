<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Students\Repository\WpdbParentBranchLookup;
use Seviye\Students\Tests\Fakes\FakeConnection;

final class WpdbParentBranchLookupTest extends TestCase
{
    public function testBranchIdsForParentReturnsDistinctBranchIdsFromTheJoinedRows(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['branch_id' => '7'], ['branch_id' => '9']];
        $lookup = new WpdbParentBranchLookup($connection);

        self::assertSame([7, 9], $lookup->branchIdsForParent(42));
    }

    public function testBranchIdsForParentReturnsEmptyWhenNoLinkedChildren(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $lookup = new WpdbParentBranchLookup($connection);

        self::assertSame([], $lookup->branchIdsForParent(42));
    }
}
