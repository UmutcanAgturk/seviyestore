<?php

declare(strict_types=1);

namespace Seviye\Core\Logging;

/**
 * All fields optional/nullable - a null field means "don't filter on this",
 * not "match nothing". Mirrors
 * Seviye\Commerce\Contracts\OrderLineItemFilter's shape. `fromDate`/`toDate`
 * are inclusive `Y-m-d` dates compared against `created_at`; `search` is a
 * substring match against the log `message`.
 */
final class LogFilter
{
    public function __construct(
        public readonly ?string $channel = null,
        public readonly ?string $level = null,
        public readonly ?int $userId = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $search = null,
        public readonly int $limit = 200
    ) {
    }
}
