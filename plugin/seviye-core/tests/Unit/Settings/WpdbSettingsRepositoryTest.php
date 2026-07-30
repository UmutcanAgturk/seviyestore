<?php

declare(strict_types=1);

namespace Seviye\Core\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Settings\WpdbSettingsRepository;
use Seviye\Core\Tests\Fakes\FakeConnection;

final class WpdbSettingsRepositoryTest extends TestCase
{
    public function testGetReturnsNullWhenKeyWasNeverSet(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbSettingsRepository($connection);

        self::assertNull($repository->get('security.admin_ip_allowlist'));
    }

    public function testGetReturnsStoredValue(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['setting_value' => "10.0.0.1\n10.0.0.2"]];
        $repository = new WpdbSettingsRepository($connection);

        self::assertSame("10.0.0.1\n10.0.0.2", $repository->get('security.admin_ip_allowlist'));
    }

    public function testSetIssuesAnUpsertQuery(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbSettingsRepository($connection);

        $repository->set('security.admin_ip_allowlist', '10.0.0.1');

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $connection->queries[0]);
        self::assertStringContainsString('10.0.0.1', $connection->queries[0]);
    }
}
