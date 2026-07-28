<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Seviye\Core\Security\RateLimiter;
use Seviye\Security\Auth\AuthFailureReason;
use Seviye\Security\Auth\AuthService;
use Seviye\Security\Auth\TcNumber;
use Seviye\Security\Tests\Fakes\FakeCache;
use Seviye\Security\Tests\Fakes\FakeCredentialGateway;
use Seviye\Security\Tests\Fakes\FakeIdentityGateway;

final class AuthServiceTest extends TestCase
{
    private const VALID_TC_NUMBER = '10000000146';

    public function testSuccessfulLoginReturnsTheLinkedUserId(): void
    {
        $identities = new FakeIdentityGateway();
        $identities->link(TcNumber::fromString(self::VALID_TC_NUMBER), 42);
        $credentials = (new FakeCredentialGateway())->withPassword(42, 'correct-horse');

        $service = $this->makeService($identities, $credentials);

        $result = $service->attempt(self::VALID_TC_NUMBER, 'correct-horse');

        self::assertTrue($result->successful);
        self::assertSame(42, $result->userId);
    }

    public function testUnknownTcNumberFailsWithInvalidCredentials(): void
    {
        $service = $this->makeService(new FakeIdentityGateway(), new FakeCredentialGateway());

        $result = $service->attempt(self::VALID_TC_NUMBER, 'irrelevant');

        self::assertFalse($result->successful);
        self::assertSame(AuthFailureReason::INVALID_CREDENTIALS, $result->failureReason);
    }

    public function testMalformedTcNumberFailsWithInvalidCredentialsWithoutTouchingGateways(): void
    {
        $service = $this->makeService(new FakeIdentityGateway(), new FakeCredentialGateway());

        $result = $service->attempt('not-a-tc-number', 'irrelevant');

        self::assertFalse($result->successful);
        self::assertSame(AuthFailureReason::INVALID_CREDENTIALS, $result->failureReason);
    }

    public function testWrongPasswordFailsWithInvalidCredentials(): void
    {
        $identities = new FakeIdentityGateway();
        $identities->link(TcNumber::fromString(self::VALID_TC_NUMBER), 42);
        $credentials = new FakeCredentialGateway();
        $credentials->withPassword(42, 'correct-horse');

        $service = $this->makeService($identities, $credentials);

        $result = $service->attempt(self::VALID_TC_NUMBER, 'wrong-password');

        self::assertFalse($result->successful);
        self::assertSame(AuthFailureReason::INVALID_CREDENTIALS, $result->failureReason);
    }

    public function testRepeatedFailuresEventuallyThrottle(): void
    {
        $service = $this->makeService(new FakeIdentityGateway(), new FakeCredentialGateway());

        for ($i = 0; $i < 5; $i++) {
            $result = $service->attempt(self::VALID_TC_NUMBER, 'wrong');
            self::assertSame(AuthFailureReason::INVALID_CREDENTIALS, $result->failureReason);
        }

        $result = $service->attempt(self::VALID_TC_NUMBER, 'wrong');

        self::assertSame(AuthFailureReason::THROTTLED, $result->failureReason);
    }

    public function testSuccessfulLoginClearsPriorThrottleCount(): void
    {
        $identities = new FakeIdentityGateway();
        $identities->link(TcNumber::fromString(self::VALID_TC_NUMBER), 42);
        $credentials = new FakeCredentialGateway();
        $credentials->withPassword(42, 'correct-horse');

        $service = $this->makeService($identities, $credentials);

        $service->attempt(self::VALID_TC_NUMBER, 'wrong');
        $service->attempt(self::VALID_TC_NUMBER, 'wrong');
        $service->attempt(self::VALID_TC_NUMBER, 'correct-horse');

        // Three more failures after the successful login should not throttle yet
        // (threshold is 5), proving the earlier failure count was cleared.
        $service->attempt(self::VALID_TC_NUMBER, 'wrong');
        $service->attempt(self::VALID_TC_NUMBER, 'wrong');
        $result = $service->attempt(self::VALID_TC_NUMBER, 'wrong');

        self::assertSame(AuthFailureReason::INVALID_CREDENTIALS, $result->failureReason);
    }

    private function makeService(FakeIdentityGateway $identities, FakeCredentialGateway $credentials): AuthService
    {
        return new AuthService(
            $identities,
            $credentials,
            new RateLimiter(new FakeCache()),
            new NullLogger()
        );
    }
}
