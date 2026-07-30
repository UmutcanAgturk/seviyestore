<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\TwoFactor;

use PHPUnit\Framework\TestCase;
use Seviye\Security\TwoFactor\PendingTwoFactorLoginService;
use Seviye\Security\Tests\Fakes\FakeCache;

final class PendingTwoFactorLoginServiceTest extends TestCase
{
    public function testBeginThenResolveReturnsTheSameUserIdAndRememberFlag(): void
    {
        $service = new PendingTwoFactorLoginService(new FakeCache());

        $token = $service->begin(42, true);
        $pending = $service->resolve($token);

        self::assertNotNull($pending);
        self::assertSame(42, $pending->userId);
        self::assertTrue($pending->remember);
    }

    public function testResolveReturnsNullForAnUnknownToken(): void
    {
        $service = new PendingTwoFactorLoginService(new FakeCache());

        self::assertNull($service->resolve('never-issued'));
    }

    public function testConsumeInvalidatesTheToken(): void
    {
        $service = new PendingTwoFactorLoginService(new FakeCache());
        $token = $service->begin(1, false);

        $service->consume($token);

        self::assertNull($service->resolve($token));
    }

    public function testEachBeginCallProducesADifferentToken(): void
    {
        $service = new PendingTwoFactorLoginService(new FakeCache());

        self::assertNotSame($service->begin(1, false), $service->begin(1, false));
    }
}
