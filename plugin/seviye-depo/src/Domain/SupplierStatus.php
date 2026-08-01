<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

enum SupplierStatus: string
{
    case ACTIVE = 'active';
    case PASSIVE = 'passive';
}
