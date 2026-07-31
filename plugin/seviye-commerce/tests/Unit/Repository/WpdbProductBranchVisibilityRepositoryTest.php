<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Commerce\Domain\ProductBranchStatus;
use Seviye\Commerce\Repository\WpdbProductBranchVisibilityRepository;
use Seviye\Commerce\Tests\Fakes\FakeConnection;

final class WpdbProductBranchVisibilityRepositoryTest extends TestCase
{
    public function testSetStatusUpsertsARow(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbProductBranchVisibilityRepository($connection);

        $repository->setStatus(5, 7, ProductBranchStatus::PASSIVE);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $connection->queries[0]);
        self::assertStringContainsString('passive', $connection->queries[0]);
    }

    public function testIsActiveForBranchDefaultsToTrueWhenNoRowExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbProductBranchVisibilityRepository($connection);

        self::assertTrue($repository->isActiveForBranch(5, 7));
    }

    public function testIsActiveForBranchReflectsAnExplicitPassiveRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['status' => 'passive']];
        $repository = new WpdbProductBranchVisibilityRepository($connection);

        self::assertFalse($repository->isActiveForBranch(5, 7));
    }

    public function testIsActiveForBranchReflectsAnExplicitActiveRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['status' => 'active']];
        $repository = new WpdbProductBranchVisibilityRepository($connection);

        self::assertTrue($repository->isActiveForBranch(5, 7));
    }

    public function testStatusesForProductKeysByBranchId(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            ['branch_id' => '7', 'status' => 'active'],
            ['branch_id' => '9', 'status' => 'passive'],
        ];
        $repository = new WpdbProductBranchVisibilityRepository($connection);

        self::assertSame(
            [7 => ProductBranchStatus::ACTIVE, 9 => ProductBranchStatus::PASSIVE],
            $repository->statusesForProduct(5)
        );
    }
}
