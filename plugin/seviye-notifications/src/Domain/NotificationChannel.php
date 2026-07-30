<?php

declare(strict_types=1);

namespace Seviye\Notifications\Domain;

enum NotificationChannel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case PANEL = 'panel';
}
