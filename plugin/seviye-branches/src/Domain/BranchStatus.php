<?php

declare(strict_types=1);

namespace Seviye\Branches\Domain;

enum BranchStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
