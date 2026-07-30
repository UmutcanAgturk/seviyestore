<?php

declare(strict_types=1);

namespace Seviye\Parents\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Parents\Repository\WpdbParentContactLookup;
use Seviye\Parents\Tests\Fakes\FakeConnection;

final class WpdbParentContactLookupTest extends TestCase
{
    public function testPhoneForReturnsThePhoneWhenOnFile(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['phone' => '05551234567']];
        $lookup = new WpdbParentContactLookup($connection);

        self::assertSame('05551234567', $lookup->phoneFor(12));
    }

    public function testPhoneForReturnsNullWhenNoProfileRowExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $lookup = new WpdbParentContactLookup($connection);

        self::assertNull($lookup->phoneFor(999));
    }

    public function testPhoneForReturnsNullWhenTheProfileHasNoPhoneOnFile(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['phone' => null]];
        $lookup = new WpdbParentContactLookup($connection);

        self::assertNull($lookup->phoneFor(12));
    }
}
