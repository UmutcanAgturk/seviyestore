<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Students\Repository\WpdbParentClassLookup;
use Seviye\Students\Tests\Fakes\FakeConnection;

final class WpdbParentClassLookupTest extends TestCase
{
    public function testClassNamesForParentReturnsDistinctClassNamesFromTheJoinedRows(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['class_name' => '5. Sınıf'], ['class_name' => '8. Sınıf']];
        $lookup = new WpdbParentClassLookup($connection);

        self::assertSame(['5. Sınıf', '8. Sınıf'], $lookup->classNamesForParent(42));
    }

    public function testClassNamesForParentReturnsEmptyWhenNoLinkedChildren(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $lookup = new WpdbParentClassLookup($connection);

        self::assertSame([], $lookup->classNamesForParent(42));
    }
}
