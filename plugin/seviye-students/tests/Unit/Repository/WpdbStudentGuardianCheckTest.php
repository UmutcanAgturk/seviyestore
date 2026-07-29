<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Students\Repository\WpdbStudentGuardianCheck;
use Seviye\Students\Tests\Fakes\FakeConnection;

final class WpdbStudentGuardianCheckTest extends TestCase
{
    public function testIsGuardianOfReturnsTrueWhenALinkRowExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['id' => '1']];
        $check = new WpdbStudentGuardianCheck($connection);

        self::assertTrue($check->isGuardianOf(42, 3));
    }

    public function testIsGuardianOfReturnsFalseWhenNoLinkRowExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $check = new WpdbStudentGuardianCheck($connection);

        self::assertFalse($check->isGuardianOf(42, 3));
    }
}
