<?php

declare(strict_types=1);

namespace Seviye\Parents\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Parents\Domain\NotificationPreference;
use Seviye\Parents\Repository\WpdbParentProfileRepository;
use Seviye\Parents\Tests\Fakes\FakeConnection;

final class WpdbParentProfileRepositoryTest extends TestCase
{
    public function testFindByUserIdReturnsNullWhenNoProfileExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbParentProfileRepository($connection);

        self::assertNull($repository->findByUserId(42));
    }

    public function testUpsertInsertsANewProfileWithoutConsentWhenNotGiven(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbParentProfileRepository($connection);

        $profile = $repository->upsert(42, '05551234567', NotificationPreference::SMS, false);

        self::assertCount(1, $connection->inserted);
        self::assertNull($connection->inserted[0][1]['kvkk_consent_at']);
        self::assertFalse($profile->hasGivenKvkkConsent());
    }

    public function testUpsertInsertsANewProfileWithConsentTimestampWhenGiven(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbParentProfileRepository($connection);

        $profile = $repository->upsert(42, '05551234567', NotificationPreference::EMAIL, true);

        self::assertNotNull($connection->inserted[0][1]['kvkk_consent_at']);
        self::assertTrue($profile->hasGivenKvkkConsent());
    }

    public function testUpsertUpdatesAnExistingProfile(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [[
            'user_id' => '42',
            'phone' => '05550000000',
            'notification_preference' => 'email',
            'kvkk_consent_at' => null,
        ]];
        $repository = new WpdbParentProfileRepository($connection);

        $repository->upsert(42, '05559999999', NotificationPreference::BOTH, false);

        self::assertCount(0, $connection->inserted);
        self::assertCount(1, $connection->queries);
    }

    public function testUpsertNeverOverwritesAnExistingConsentTimestamp(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [[
            'user_id' => '42',
            'phone' => '05550000000',
            'notification_preference' => 'email',
            'kvkk_consent_at' => '2020-01-01 00:00:00',
        ]];
        $repository = new WpdbParentProfileRepository($connection);

        // Calling upsert again with kvkkConsentGiven=false must not clear
        // the previously recorded consent - it is expected to persist even
        // when a later request omits the consent flag.
        $profile = $repository->upsert(42, '05550000000', NotificationPreference::EMAIL, false);

        self::assertSame('2020-01-01 00:00:00', $profile->kvkkConsentAt);
    }
}
