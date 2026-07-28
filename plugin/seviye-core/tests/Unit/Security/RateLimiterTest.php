<?php

declare(strict_types=1);

namespace Seviye\Core\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Security\RateLimiter;
use Seviye\Core\Tests\Fakes\FakeCache;

final class RateLimiterTest extends TestCase
{
    public function testHitIncrementsAttemptsAndTooManyAttemptsRespectsThreshold(): void
    {
        $limiter = new RateLimiter(new FakeCache());
        $key = 'login:12345678901';

        self::assertFalse($limiter->tooManyAttempts($key, 3));

        $limiter->hit($key, 60);
        $limiter->hit($key, 60);
        self::assertFalse($limiter->tooManyAttempts($key, 3));

        $limiter->hit($key, 60);
        self::assertTrue($limiter->tooManyAttempts($key, 3));
    }

    public function testClearResetsAttempts(): void
    {
        $limiter = new RateLimiter(new FakeCache());
        $key = 'login:12345678901';

        $limiter->hit($key, 60);
        $limiter->clear($key);

        self::assertSame(0, $limiter->attempts($key));
    }
}
