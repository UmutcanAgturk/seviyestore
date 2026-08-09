<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

/**
 * The subject/body pair {@see AbandonedCartReminderBuilder} produces - same
 * plain-DTO idiom as {@see WeeklyDigestMessage}.
 */
final class AbandonedCartReminderMessage
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body
    ) {
    }
}
