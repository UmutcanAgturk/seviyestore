<?php

declare(strict_types=1);

namespace Seviye\Branches\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Branches\Repository\WpdbBranchMembershipRepository;
use Seviye\Branches\Tests\Fakes\FakeConnection;

final class WpdbBranchMembershipRepositoryTest extends TestCase
{
    public function testBranchIdForUserReturnsNullWhenUnassigned(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchMembershipRepository($connection);

        self::assertNull($repository->branchIdForUser(42));
    }

    public function testAssignInsertsWhenTheUserHasNoExistingMembership(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchMembershipRepository($connection);

        $repository->assign(42, 7);

        self::assertCount(1, $connection->inserted);
        self::assertSame(7, $connection->inserted[0][1]['branch_id']);
        self::assertSame(42, $connection->inserted[0][1]['user_id']);
        self::assertCount(0, $connection->queries);
    }

    public function testAssignUpdatesWhenTheUserAlreadyHasAMembership(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['branch_id' => '3']];
        $repository = new WpdbBranchMembershipRepository($connection);

        $repository->assign(42, 9);

        self::assertCount(0, $connection->inserted);
        self::assertCount(1, $connection->queries);
    }

    public function testUnassignIssuesADeleteQuery(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbBranchMembershipRepository($connection);

        $repository->unassign(42);

        self::assertCount(1, $connection->queries);
    }

    public function testUsersForBranchReturnsEveryMatchingUserId(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['user_id' => '5'], ['user_id' => '9']];
        $repository = new WpdbBranchMembershipRepository($connection);

        self::assertSame([5, 9], $repository->usersForBranch(7));
    }

    public function testUsersForBranchReturnsEmptyArrayWhenNoneAssigned(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchMembershipRepository($connection);

        self::assertSame([], $repository->usersForBranch(7));
    }
}
