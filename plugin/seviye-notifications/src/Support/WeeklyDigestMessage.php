<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

/**
 * The subject/body pair {@see WeeklyDigestBuilder} produces - kept as a
 * plain DTO rather than returning a two-element array so the return type
 * documents itself.
 */
final class WeeklyDigestMessage
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body
    ) {
    }
}
