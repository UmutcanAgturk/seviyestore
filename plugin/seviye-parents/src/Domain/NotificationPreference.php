<?php

declare(strict_types=1);

namespace Seviye\Parents\Domain;

enum NotificationPreference: string
{
    case SMS = 'sms';
    case EMAIL = 'email';
    case BOTH = 'both';
}
