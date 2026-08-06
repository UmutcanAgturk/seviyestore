<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\Token;

use PHPUnit\Framework\TestCase;
use Seviye\Security\Tests\Fakes\FakeClock;
use Seviye\Security\Tests\Fakes\FakeTokenGateway;
use Seviye\Security\Token\PasswordTokenPurpose;
use Seviye\Security\Token\PasswordTokenService;

final class PasswordTokenServiceTest extends TestCase
{
    public function testIssuedTokenCanBeRedeemedOnce(): void
    {
        $clock = new FakeClock();
        $service = new PasswordTokenService(new FakeTokenGateway(), $clock);

        $token = $service->issue(42, PasswordTokenPurpose::RESET);
        $record = $service->redeem($token);

        self::assertNotNull($record);
        self::assertSame(42, $record->userId);
        self::assertSame(PasswordTokenPurpose::RESET, $record->purpose);
    }

    public function testTokenCannotBeRedeemedTwice(): void
    {
        $service = new PasswordTokenService(new FakeTokenGateway(), new FakeClock());

        $token = $service->issue(42, PasswordTokenPurpose::RESET);
        $service->redeem($token);

        self::assertNull($service->redeem($token));
    }

    public function testExpiredTokenIsRejectedAndBurned(): void
    {
        $clock = new FakeClock();
        $service = new PasswordTokenService(new FakeTokenGateway(), $clock);

        $token = $service->issue(42, PasswordTokenPurpose::RESET);
        $clock->advance('+2 hours');

        self::assertNull($service->redeem($token));
        // Burned even though expired: replaying it again must still fail.
        $clock->advance('-2 hours');
        self::assertNull($service->redeem($token));
    }

    public function testUnknownTokenIsRejected(): void
    {
        $service = new PasswordTokenService(new FakeTokenGateway(), new FakeClock());

        self::assertNull($service->redeem('this-token-was-never-issued'));
    }

    public function testDifferentUsersCanHoldIndependentTokensConcurrently(): void
    {
        $service = new PasswordTokenService(new FakeTokenGateway(), new FakeClock());

        $tokenA = $service->issue(1, PasswordTokenPurpose::RESET);
        $tokenB = $service->issue(2, PasswordTokenPurpose::RESET);

        self::assertSame(1, $service->redeem($tokenA)?->userId);
        self::assertSame(2, $service->redeem($tokenB)?->userId);
    }
}
