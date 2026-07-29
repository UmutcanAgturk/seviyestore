<?php

declare(strict_types=1);

namespace Seviye\Students\Domain;

enum StudentStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
