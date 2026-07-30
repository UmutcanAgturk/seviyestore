<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\TwoFactor;

use PHPUnit\Framework\TestCase;
use Seviye\Security\TwoFactor\Encryptor;
use Seviye\Security\TwoFactor\Totp;
use Seviye\Security\TwoFactor\TwoFactorService;
use Seviye\Security\Tests\Fakes\FakeTwoFactorGateway;

final class TwoFactorServiceTest extends TestCase
{
    private function service(): TwoFactorService
    {
        return new TwoFactorService(new FakeTwoFactorGateway(), Encryptor::fromSecret('test-key'));
    }

    public function testUserIsNotEnabledBeforeAnySetup(): void
    {
        self::assertFalse($this->service()->isEnabledForUser(1));
    }

    public function testBeginSetupDoesNotEnableUntilConfirmed(): void
    {
        $service = $this->service();
        $service->beginSetup(1, 'sube.muduru');

        self::assertFalse($service->isEnabledForUser(1));
    }

    public function testConfirmSetupWithAValidCodeEnablesTwoFactor(): void
    {
        $service = $this->service();
        $setup = $service->beginSetup(1, 'sube.muduru');

        self::assertTrue($service->confirmSetup(1, Totp::generate($setup->secret)));
        self::assertTrue($service->isEnabledForUser(1));
    }

    public function testConfirmSetupWithAWrongCodeDoesNotEnable(): void
    {
        $service = $this->service();
        $service->beginSetup(1, 'sube.muduru');

        self::assertFalse($service->confirmSetup(1, '000000'));
        self::assertFalse($service->isEnabledForUser(1));
    }

    public function testConfirmSetupWithoutAPriorSetupFails(): void
    {
        self::assertFalse($this->service()->confirmSetup(999, '123456'));
    }

    public function testVerifyCodeFailsBeforeSetupIsConfirmed(): void
    {
        $service = $this->service();
        $setup = $service->beginSetup(1, 'sube.muduru');

        self::assertFalse($service->verifyCode(1, Totp::generate($setup->secret)));
    }

    public function testVerifyCodeSucceedsOnceConfirmed(): void
    {
        $service = $this->service();
        $setup = $service->beginSetup(1, 'sube.muduru');
        $service->confirmSetup(1, Totp::generate($setup->secret));

        self::assertTrue($service->verifyCode(1, Totp::generate($setup->secret)));
    }

    public function testVerifyCodeRejectsAWrongCodeAfterConfirmation(): void
    {
        $service = $this->service();
        $setup = $service->beginSetup(1, 'sube.muduru');
        $service->confirmSetup(1, Totp::generate($setup->secret));

        self::assertFalse($service->verifyCode(1, '000000'));
    }

    public function testDisableRemovesTheSecretEntirely(): void
    {
        $service = $this->service();
        $setup = $service->beginSetup(1, 'sube.muduru');
        $service->confirmSetup(1, Totp::generate($setup->secret));

        $service->disable(1);

        self::assertFalse($service->isEnabledForUser(1));
        self::assertFalse($service->verifyCode(1, Totp::generate($setup->secret)));
    }

    public function testRestartingSetupInvalidatesThePreviouslyConfirmedSecret(): void
    {
        $service = $this->service();
        $firstSetup = $service->beginSetup(1, 'sube.muduru');
        $service->confirmSetup(1, Totp::generate($firstSetup->secret));
        self::assertTrue($service->isEnabledForUser(1));

        $service->beginSetup(1, 'sube.muduru');

        self::assertFalse($service->isEnabledForUser(1));
        self::assertFalse($service->verifyCode(1, Totp::generate($firstSetup->secret)));
    }

    public function testOtpauthUriCarriesTheSecretAndPlatformIssuer(): void
    {
        $setup = $this->service()->beginSetup(1, 'genel.merkez');

        self::assertStringStartsWith('otpauth://totp/', $setup->otpauthUri);
        self::assertStringContainsString('secret=' . $setup->secret, $setup->otpauthUri);
        self::assertStringContainsString(rawurlencode('Seviye Commerce Platform'), $setup->otpauthUri);
    }
}
