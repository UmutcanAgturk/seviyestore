<?php

declare(strict_types=1);

namespace Seviye\Core\Logging;

/**
 * Read model for one scp_logs row, as returned by {@see WpdbLogQuery} to
 * Http\ActivityLogRestController - `context` is already json_decode()d
 * (DatabaseLogger persists it as JSON text), `userName` is resolved from
 * `userId` at query time since scp_logs itself only stores the numeric id.
 */
final class LogEntry
{
    /**
     * @param array<string, mixed>|null $context
     */
    public function __construct(
        public readonly int $id,
        public readonly string $channel,
        public readonly string $level,
        public readonly string $message,
        public readonly ?array $context,
        public readonly ?int $userId,
        public readonly ?string $userName,
        public readonly ?string $ipAddress,
        public readonly string $createdAt
    ) {
    }
}
