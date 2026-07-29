<?php

declare(strict_types=1);

namespace Seviye\Pricing\Domain;

enum PriceRuleStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
