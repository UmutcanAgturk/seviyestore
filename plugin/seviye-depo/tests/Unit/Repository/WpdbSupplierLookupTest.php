<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Depo\Repository\WpdbSupplierLookup;
use Seviye\Depo\Tests\Fakes\FakeConnection;

final class WpdbSupplierLookupTest extends TestCase
{
    public function testFindReturnsNullWhenNoRowMatches(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $lookup = new WpdbSupplierLookup($connection);

        self::assertNull($lookup->find(999));
    }

    public function testFindHydratesTheSupplierSummary(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['id' => '3', 'name' => 'Okul Tekstil']];
        $lookup = new WpdbSupplierLookup($connection);

        $summary = $lookup->find(3);

        self::assertNotNull($summary);
        self::assertSame(3, $summary->id);
        self::assertSame('Okul Tekstil', $summary->name);
    }
}
