<?php

declare(strict_types=1);

namespace Seviye\Students\Domain;

enum ParentRelationship: string
{
    case MOTHER = 'anne';
    case FATHER = 'baba';
    case GUARDIAN = 'vasi';
}
