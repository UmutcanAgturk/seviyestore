<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Students\Repository\WpdbStudentLookup;
use Seviye\Students\Tests\Fakes\FakeConnection;

final class WpdbStudentLookupTest extends TestCase
{
    public function testFindReturnsASummaryWhenTheStudentExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            ['id' => '3', 'branch_id' => '7', 'first_name' => 'Ayşe', 'last_name' => 'Yılmaz'],
        ];
        $lookup = new WpdbStudentLookup($connection);

        $summary = $lookup->find(3);

        self::assertNotNull($summary);
        self::assertSame(3, $summary->id);
        self::assertSame(7, $summary->branchId);
        self::assertSame('Ayşe', $summary->firstName);
        self::assertSame('Yılmaz', $summary->lastName);
    }

    public function testFindReturnsNullWhenTheStudentDoesNotExist(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $lookup = new WpdbStudentLookup($connection);

        self::assertNull($lookup->find(999));
    }

    public function testExistsReflectsWhetherFindReturnsAResult(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            ['id' => '3', 'branch_id' => '7', 'first_name' => 'Ayşe', 'last_name' => 'Yılmaz'],
        ];
        $lookup = new WpdbStudentLookup($connection);

        self::assertTrue($lookup->exists(3));

        $connection->resultsToReturn = [];
        self::assertFalse($lookup->exists(999));
    }
}
