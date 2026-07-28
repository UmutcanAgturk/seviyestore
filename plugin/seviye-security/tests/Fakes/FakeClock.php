<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Fakes;

use DateTimeImmutable;
use Seviye\Security\Token\ClockInterface;

final class FakeClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-01-01 00:00:00'))
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
