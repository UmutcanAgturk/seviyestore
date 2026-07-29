<?php

declare(strict_types=1);

namespace Seviye\Pricing\Contracts;

/**
 * Which tier of the priority chain produced a {@see ResolvedPrice} -
 * lets a caller (e.g. Seviye Commerce showing a Veli why they see a given
 * price) explain the result instead of just returning a bare number.
 */
enum PriceSource: string
{
    case STUDENT = 'student';
    case BRANCH = 'branch';
    case GENERAL = 'general';
    case FALLBACK = 'fallback';
}
