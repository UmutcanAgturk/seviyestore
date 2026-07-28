<?php

declare(strict_types=1);

namespace Seviye\Security\Token;

use DateTimeImmutable;

/**
 * Abstracts "now" so token expiry logic can be tested deterministically
 * instead of racing the real clock.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
