<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Seviye\Security\Auth\PasswordPolicy;

final class PasswordPolicyTest extends TestCase
{
    public function testRejectsPasswordsShorterThanTheMinimumLength(): void
    {
        self::assertFalse(PasswordPolicy::isAcceptable('short1'));
    }

    public function testAcceptsPasswordsAtOrAboveTheMinimumLength(): void
    {
        self::assertTrue(PasswordPolicy::isAcceptable('exactly8'));
        self::assertTrue(PasswordPolicy::isAcceptable('a-much-longer-passphrase'));
    }
}
