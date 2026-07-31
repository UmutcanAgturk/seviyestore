<?php

declare(strict_types=1);

namespace Seviye\Commerce\Domain;

enum ProductBranchStatus: string
{
    case ACTIVE = 'active';
    case PASSIVE = 'passive';
}
