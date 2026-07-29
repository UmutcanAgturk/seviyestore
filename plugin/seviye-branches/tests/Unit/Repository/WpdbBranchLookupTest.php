<?php

declare(strict_types=1);

namespace Seviye\Branches\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Branches\Repository\WpdbBranchLookup;
use Seviye\Branches\Tests\Fakes\FakeConnection;

final class WpdbBranchLookupTest extends TestCase
{
    public function testFindReturnsASummaryWhenTheBranchExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['id' => '7', 'name' => 'Kadıköy Şubesi', 'slug' => 'kadikoy-subesi', 'commission_rate' => '12.50']];
        $lookup = new WpdbBranchLookup($connection);

        $summary = $lookup->find(7);

        self::assertNotNull($summary);
        self::assertSame(7, $summary->id);
        self::assertSame('Kadıköy Şubesi', $summary->name);
        self::assertSame('kadikoy-subesi', $summary->slug);
        self::assertSame(12.5, $summary->commissionRate);
    }

    public function testFindReturnsNullWhenTheBranchDoesNotExist(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $lookup = new WpdbBranchLookup($connection);

        self::assertNull($lookup->find(999));
    }

    public function testExistsReflectsWhetherFindReturnsAResult(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['id' => '7', 'name' => 'Kadıköy Şubesi', 'slug' => 'kadikoy-subesi', 'commission_rate' => '12.50']];
        $lookup = new WpdbBranchLookup($connection);

        self::assertTrue($lookup->exists(7));

        $connection->resultsToReturn = [];
        self::assertFalse($lookup->exists(999));
    }
}
