<?php

declare(strict_types=1);

namespace Seviye\Api\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Seviye\Api\Auth\ApiKeyAuthenticator;
use Seviye\Api\Support\ApiKeyGenerator;
use Seviye\Api\Tests\Fakes\FakeApiKeyRepository;
use Seviye\Api\Tests\Fakes\FakeCache;
use Seviye\Core\Security\RateLimiter;

final class ApiKeyAuthenticatorTest extends TestCase
{
    public function testValidKeyAuthenticatesAsItsOwningUser(): void
    {
        $repository = new FakeApiKeyRepository();
        $generated = ApiKeyGenerator::generate();
        $repository->create(12, 'Mobil Uygulama', $generated->prefix, $generated->hash);
        $authenticator = $this->makeAuthenticator($repository);

        $result = $authenticator->authenticate($generated->plainKey, '203.0.113.5');

        self::assertTrue($result->isSuccessful());
        self::assertSame(12, $result->userId());
        self::assertSame([1], $repository->touchedIds);
    }

    public function testUnknownKeyIsInvalid(): void
    {
        $authenticator = $this->makeAuthenticator(new FakeApiKeyRepository());

        $result = $authenticator->authenticate('scp_live_doesnotexist', '203.0.113.5');

        self::assertFalse($result->isSuccessful());
        self::assertFalse($result->isThrottled());
        self::assertNull($result->userId());
    }

    public function testRevokedKeyIsInvalid(): void
    {
        $repository = new FakeApiKeyRepository();
        $generated = ApiKeyGenerator::generate();
        $created = $repository->create(12, 'Eski Entegrasyon', $generated->prefix, $generated->hash);
        $repository->revoke($created->id);
        $authenticator = $this->makeAuthenticator($repository);

        $result = $authenticator->authenticate($generated->plainKey, '203.0.113.5');

        self::assertFalse($result->isSuccessful());
    }

    public function testRepeatedInvalidAttemptsFromTheSameIpAreThrottled(): void
    {
        $authenticator = $this->makeAuthenticator(new FakeApiKeyRepository());

        for ($i = 0; $i < 10; $i++) {
            $authenticator->authenticate('scp_live_wrong', '203.0.113.5');
        }

        $result = $authenticator->authenticate('scp_live_wrong', '203.0.113.5');

        self::assertTrue($result->isThrottled());
    }

    public function testThrottleIsScopedPerIp(): void
    {
        $authenticator = $this->makeAuthenticator(new FakeApiKeyRepository());

        for ($i = 0; $i < 10; $i++) {
            $authenticator->authenticate('scp_live_wrong', '203.0.113.5');
        }

        $result = $authenticator->authenticate('scp_live_wrong', '198.51.100.9');

        self::assertFalse($result->isThrottled());
    }

    public function testSuccessfulAuthenticationClearsAnyPriorThrottleCount(): void
    {
        $repository = new FakeApiKeyRepository();
        $generated = ApiKeyGenerator::generate();
        $repository->create(12, 'Mobil Uygulama', $generated->prefix, $generated->hash);
        $authenticator = $this->makeAuthenticator($repository);

        for ($i = 0; $i < 5; $i++) {
            $authenticator->authenticate('scp_live_wrong', '203.0.113.5');
        }

        $result = $authenticator->authenticate($generated->plainKey, '203.0.113.5');

        self::assertTrue($result->isSuccessful());
    }

    private function makeAuthenticator(FakeApiKeyRepository $repository): ApiKeyAuthenticator
    {
        return new ApiKeyAuthenticator($repository, new RateLimiter(new FakeCache()));
    }
}
