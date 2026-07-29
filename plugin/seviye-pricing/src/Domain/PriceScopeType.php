<?php

declare(strict_types=1);

namespace Seviye\Pricing\Domain;

enum PriceScopeType: string
{
    /** Platform-wide default override - no branch/student target. */
    case GENERAL = 'general';

    /** Applies to every student of one branch. */
    case BRANCH = 'branch';

    /** Applies to exactly one student - highest priority. */
    case STUDENT = 'student';
}
